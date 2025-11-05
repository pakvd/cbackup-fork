/*
 * This file is part of cBackup, network equipment configuration backup tool
 * Copyright (C) 2017, Oļegs Čapligins, Imants Černovs, Dmitrijs Galočkins
 *
 * cBackup is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
package core;

import org.apache.sshd.server.SshServer;
import org.apache.sshd.server.auth.password.PasswordAuthenticator;
import org.apache.sshd.server.command.Command;
import org.apache.sshd.server.keyprovider.SimpleGeneratorHostKeyProvider;
import org.apache.sshd.server.session.ServerSession;
import org.apache.sshd.server.shell.ShellFactory;
import org.apache.sshd.server.channel.ChannelSession;
import org.apache.sshd.common.config.keys.KeyUtils;
import org.apache.sshd.common.keyprovider.KeyPairProvider;
import org.apache.sshd.common.session.SessionContext;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;

import javax.annotation.PostConstruct;
import javax.annotation.PreDestroy;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.security.GeneralSecurityException;
import java.security.KeyPair;
import java.security.KeyPairGenerator;
import java.util.Collections;

/**
 * SSH Shell Server using Apache MINA SSHD
 */
@Component
@org.springframework.context.annotation.DependsOn("scheduler")
public class SshShellServer {

    @Autowired
    private Scheduler scheduler;

    @Value("${sshd.shell.enabled:false}")
    private boolean enabled;

    @Value("${sshd.shell.port:8437}")
    private int port;

    @Value("${sshd.shell.host:0.0.0.0}")
    private String host;

    @Value("${sshd.shell.username:cbadmin}")
    private String username;

    @Value("${sshd.shell.password:KqPOPh2Lf}")
    private String password;

    private SshServer sshd;

    @PostConstruct
    public void start() {
        if (!enabled) {
            System.out.println("SSH Shell Server is disabled");
            return;
        }

        if (scheduler == null) {
            System.err.println("ERROR: Scheduler is not initialized. Cannot start SSH Shell Server.");
            return;
        }

        try {
            sshd = SshServer.setUpDefaultServer();
            sshd.setHost(host);
            sshd.setPort(port);

            // Use /app/.ssh for host key storage (works in Docker)
            // Fallback to user.home if /app doesn't exist (for non-Docker environments)
            String homeDir = System.getProperty("user.home");
            Path hostKeyPath;
            if (Files.exists(Paths.get("/app"))) {
                // Docker environment
                hostKeyPath = Paths.get("/app", ".ssh", "cbackup_hostkey");
            } else {
                // Non-Docker environment
                hostKeyPath = Paths.get(homeDir, ".ssh", "cbackup_hostkey");
            }
            
            if (Files.notExists(hostKeyPath.getParent())) {
                Files.createDirectories(hostKeyPath.getParent());
            }
            
            // Delete all old keys (including ECDSA) to force RSA regeneration
            // phpseclib 2.0.9 only supports ssh-rsa, so we must use RSA keys
            try {
                if (Files.exists(hostKeyPath)) {
                    Files.delete(hostKeyPath);
                    System.out.println("Deleted old SSH host key for regeneration");
                }
                // Also delete any .pub files
                Path hostKeyPubPath = Paths.get(hostKeyPath.toString() + ".pub");
                if (Files.exists(hostKeyPubPath)) {
                    Files.delete(hostKeyPubPath);
                }
            } catch (IOException e) {
                System.err.println("Warning: Could not delete old host key: " + e.getMessage());
            }
            
            System.out.println("SSH Host key path: " + hostKeyPath);
            
            // Create a simple RSA-only key provider
            // phpseclib 2.0.9 only supports ssh-rsa, so we must use RSA keys
            final Path finalHostKeyPath = hostKeyPath;
            KeyPairProvider keyProvider = new KeyPairProvider() {
                private KeyPair cachedRsaKey = null;
                private final Object lock = new Object();
                
                @Override
                public KeyPair loadKey(SessionContext session, String keyType) throws IOException, GeneralSecurityException {
                    // Always return RSA key regardless of requested type
                    synchronized (lock) {
                        if (cachedRsaKey == null) {
                            // Generate RSA key directly if file doesn't exist
                            if (!Files.exists(finalHostKeyPath)) {
                                System.out.println("Generating new RSA host key (2048 bits)");
                                KeyPairGenerator keyGen = KeyPairGenerator.getInstance("RSA");
                                keyGen.initialize(2048);
                                cachedRsaKey = keyGen.generateKeyPair();
                                
                                // Save the key using SimpleGeneratorHostKeyProvider for persistence
                                SimpleGeneratorHostKeyProvider saver = new SimpleGeneratorHostKeyProvider(finalHostKeyPath);
                                // Force save by loading with RSA type
                                saver.loadKey(session, KeyUtils.RSA_ALGORITHM);
                                // Note: This might still generate ECDSA, so we'll use our generated key
                                System.out.println("RSA key generated in memory");
                            } else {
                                // Try to load existing key - but force RSA
                                SimpleGeneratorHostKeyProvider loader = new SimpleGeneratorHostKeyProvider(finalHostKeyPath);
                                // Try to load RSA, but if it fails, generate new one
                                try {
                                    cachedRsaKey = loader.loadKey(session, KeyUtils.RSA_ALGORITHM);
                                    // Verify it's actually RSA
                                    if (KeyUtils.getKeyType(cachedRsaKey).equals(KeyUtils.RSA_ALGORITHM)) {
                                        System.out.println("RSA host key loaded from file");
                                    } else {
                                        System.out.println("Existing key is not RSA, generating new RSA key");
                                        KeyPairGenerator keyGen = KeyPairGenerator.getInstance("RSA");
                                        keyGen.initialize(2048);
                                        cachedRsaKey = keyGen.generateKeyPair();
                                    }
                                } catch (Exception e) {
                                    System.out.println("Could not load existing key as RSA, generating new: " + e.getMessage());
                                    KeyPairGenerator keyGen = KeyPairGenerator.getInstance("RSA");
                                    keyGen.initialize(2048);
                                    cachedRsaKey = keyGen.generateKeyPair();
                                }
                            }
                        }
                        return cachedRsaKey;
                    }
                }
                
                @Override
                public Iterable<KeyPair> loadKeys(SessionContext session) {
                    try {
                        KeyPair rsaKey = loadKey(session, KeyUtils.RSA_ALGORITHM);
                        return Collections.singletonList(rsaKey);
                    } catch (Exception e) {
                        System.err.println("Error loading RSA key: " + e.getMessage());
                        e.printStackTrace();
                        return Collections.emptyList();
                    }
                }
            };
            
            sshd.setKeyPairProvider(keyProvider);

            // Set password authenticator
            sshd.setPasswordAuthenticator(new PasswordAuthenticator() {
                @Override
                public boolean authenticate(String username, String password, ServerSession session) {
                    return SshShellServer.this.username.equals(username) && 
                           SshShellServer.this.password.equals(password);
                }
            });

            // Set shell factory - use custom command handler
            sshd.setShellFactory(new ShellFactory() {
                @Override
                public Command createShell(ChannelSession channel) {
                    try {
                        if (scheduler == null) {
                            System.err.println("ERROR: Scheduler is null when creating shell!");
                            throw new IllegalStateException("Scheduler is not initialized");
                        }
                        System.out.println("Creating shell for channel session");
                        return new CbackupShellHandler(scheduler);
                    } catch (Exception e) {
                        System.err.println("ERROR creating shell: " + e.getMessage());
                        e.printStackTrace();
                        throw new RuntimeException("Failed to create shell", e);
                    }
                }
            });

            // Start server
            sshd.start();
            System.out.println("SSH Shell Server started successfully on " + host + ":" + port);
        } catch (Exception e) {
            System.err.println("Failed to start SSH Shell Server: " + e.getMessage());
            e.printStackTrace();
            // Don't throw exception - let application continue even if SSH fails
        }
    }

    @PreDestroy
    public void stop() {
        if (sshd != null && sshd.isStarted()) {
            try {
                sshd.stop();
                System.out.println("SSH Shell Server stopped");
            } catch (IOException e) {
                System.err.println("Error stopping SSH Shell Server: " + e.getMessage());
            }
        }
    }

}

