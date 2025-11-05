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
            
            // Generate RSA key immediately to avoid race conditions during connection
            System.out.println("Generating RSA host key (2048 bits)...");
            KeyPairGenerator keyGen = KeyPairGenerator.getInstance("RSA");
            keyGen.initialize(2048);
            final KeyPair rsaKeyPair = keyGen.generateKeyPair();
            
            // Verify it's RSA
            String actualType = KeyUtils.getKeyType(rsaKeyPair);
            if (!KeyUtils.RSA_ALGORITHM.equals(actualType)) {
                throw new GeneralSecurityException("Generated key is not RSA: " + actualType);
            }
            System.out.println("RSA host key generated successfully (type: " + actualType + ")");
            
            // Create a simple RSA-only key provider that always returns the pre-generated RSA key
            KeyPairProvider keyProvider = new KeyPairProvider() {
                @Override
                public KeyPair loadKey(SessionContext session, String keyType) throws IOException, GeneralSecurityException {
                    // Always return RSA key regardless of requested type
                    return rsaKeyPair;
                }
                
                @Override
                public Iterable<KeyPair> loadKeys(SessionContext session) {
                    // Always return the pre-generated RSA key
                    return Collections.singletonList(rsaKeyPair);
                }
            };
            
            sshd.setKeyPairProvider(keyProvider);

            // Set password authenticator
            sshd.setPasswordAuthenticator(new PasswordAuthenticator() {
                @Override
                public boolean authenticate(String username, String password, ServerSession session) {
                    boolean authenticated = SshShellServer.this.username.equals(username) && 
                                          SshShellServer.this.password.equals(password);
                    if (!authenticated) {
                        System.out.println("SSH authentication failed - Username: '" + username + 
                                         "', Expected: '" + SshShellServer.this.username + 
                                         "', Password match: " + SshShellServer.this.password.equals(password));
                    } else {
                        System.out.println("SSH authentication successful for user: " + username);
                    }
                    return authenticated;
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

