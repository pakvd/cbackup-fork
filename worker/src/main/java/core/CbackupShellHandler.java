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

import org.apache.sshd.server.channel.ChannelSession;
import org.apache.sshd.server.command.Command;
import org.apache.sshd.server.Environment;
import org.apache.sshd.server.ExitCallback;

import java.io.*;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/**
 * Command handler for cbackup shell commands
 */
public class CbackupShellHandler implements Command {

    private final Scheduler scheduler;
    private InputStream in;
    private OutputStream out;
    private OutputStream err;
    private ExitCallback callback;
    private ExecutorService executor;

    public CbackupShellHandler(Scheduler scheduler) {
        this.scheduler = scheduler;
    }

    @Override
    public void setInputStream(InputStream in) {
        this.in = in;
    }

    @Override
    public void setOutputStream(OutputStream out) {
        this.out = out;
    }

    @Override
    public void setErrorStream(OutputStream err) {
        this.err = err;
    }

    @Override
    public void setExitCallback(ExitCallback callback) {
        this.callback = callback;
    }

    @Override
    public void start(ChannelSession channel, Environment env) throws IOException {
        System.out.println("CbackupShellHandler.start() called");
        if (scheduler == null) {
            throw new IOException("Scheduler is not initialized");
        }
        executor = Executors.newSingleThreadExecutor();
        executor.submit(() -> {
            System.out.println("Shell handler thread started");
            PrintWriter writer = null;
            PrintWriter errorWriter = null;
            BufferedReader reader = null;
            try {
                // Initialize streams
                // Use InputStreamReader with explicit charset to ensure proper character reading
                reader = new BufferedReader(new InputStreamReader(in, java.nio.charset.StandardCharsets.UTF_8));
                writer = new PrintWriter(new OutputStreamWriter(out, java.nio.charset.StandardCharsets.UTF_8), true);
                errorWriter = new PrintWriter(new OutputStreamWriter(err, java.nio.charset.StandardCharsets.UTF_8), true);
                
                System.out.println("Streams initialized - reader ready, writer ready, errorWriter ready");
                System.err.println("Streams initialized (stderr)");
                System.err.flush();
                
                System.out.println("Streams initialized, sending welcome message");
                
                // Send welcome message and prompt immediately
                writer.println("cBackup Shell - Type 'help' for available commands");
                writer.flush();
                writer.print("cbackup> ");
                writer.flush();
                
                System.out.println("Welcome message sent");
                System.err.println("Welcome message sent (stderr)");

                String line;
                System.out.println("Waiting for input...");
                System.err.println("Waiting for input... (stderr)");
                System.err.flush();
                
                while (true) {
                    try {
                        System.out.println("About to read line from reader...");
                        System.err.println("About to read line from reader... (stderr)");
                        System.err.flush();
                        System.out.flush();
                        
                        // Force flush to ensure logs are visible
                        java.io.PrintStream ps = System.out;
                        ps.flush();
                        
                        line = reader.readLine();
                        
                        if (line == null) {
                            System.out.println("readLine() returned null - connection closed");
                            System.err.println("readLine() returned null (stderr)");
                            System.err.flush();
                            System.out.flush();
                            break;
                        }
                        
                        System.out.println("Raw input line received: [" + line + "] (length: " + line.length() + ")");
                        System.err.println("Raw input line received: [" + line + "] (length: " + line.length() + ") (stderr)");
                        System.err.flush();
                        System.out.flush();
                    
                    if (line.trim().isEmpty()) {
                        writer.print("cbackup> ");
                        writer.flush();
                        continue;
                    }

                    String[] parts = line.trim().split("\\s+");
                    System.out.println("Parts count: " + parts.length + ", parts: " + java.util.Arrays.toString(parts));
                    System.err.println("Parts count: " + parts.length + ", parts: " + java.util.Arrays.toString(parts) + " (stderr)");
                    
                    String command = parts[0].toLowerCase();
                    String args;
                    
                    System.out.println("First part (command): [" + command + "], checking if equals 'cbackup'");
                    System.err.println("First part (command): [" + command + "], checking if equals 'cbackup' (stderr)");
                    
                    // Handle "cbackup" prefix - if command is "cbackup", use next part as command
                    if ("cbackup".equals(command) && parts.length > 1) {
                        command = parts[1].toLowerCase();
                        // Build args from remaining parts
                        StringBuilder argsBuilder = new StringBuilder();
                        for (int i = 2; i < parts.length; i++) {
                            if (argsBuilder.length() > 0) argsBuilder.append(" ");
                            argsBuilder.append(parts[i]);
                        }
                        args = argsBuilder.toString();
                        System.out.println("Received command with cbackup prefix: " + command + " with args: [" + args + "]");
                        System.err.println("Received command with cbackup prefix: " + command + " with args: [" + args + "] (stderr)");
                    } else {
                        args = parts.length > 1 ? line.substring(parts[0].length()).trim() : "";
                        System.out.println("Received command (no prefix): " + command + " with args: [" + args + "]");
                        System.err.println("Received command (no prefix): " + command + " with args: [" + args + "] (stderr)");
                    }
                    
                    System.out.println("Calling processCommand with command=[" + command + "], args=[" + args + "]");
                    System.err.println("Calling processCommand with command=[" + command + "], args=[" + args + "] (stderr)");
                    
                    String result = processCommand(command, args, errorWriter);
                    if (result != null && !result.isEmpty()) {
                        System.out.println("Command result length: " + result.length());
                        System.err.println("Command result length: " + result.length() + " (stderr)");
                        writer.println(result);
                        writer.flush();
                    } else {
                        System.out.println("Command returned null or empty result");
                        System.err.println("Command returned null or empty result (stderr)");
                    }
                    writer.print("cbackup> ");
                    writer.flush();
                    System.out.println("Prompt sent after command");
                    System.err.println("Prompt sent after command (stderr)");
                    System.err.flush();
                    } catch (IOException e) {
                        System.out.println("IOException while reading line: " + e.getMessage());
                        System.err.println("IOException while reading line: " + e.getMessage() + " (stderr)");
                        System.err.flush();
                        break;
                    }
                }
            } catch (Exception e) {
                // Connection closed or other error - this is normal when client disconnects
                System.out.println("SSH connection closed or error: " + e.getMessage());
                System.err.println("Error in SSH handler: " + e.getMessage());
                e.printStackTrace();
            } finally {
                // Close streams
                try {
                    if (reader != null) reader.close();
                    if (writer != null) writer.close();
                    if (errorWriter != null) errorWriter.close();
                } catch (IOException e) {
                    System.err.println("Error closing streams: " + e.getMessage());
                }
                
                if (callback != null) {
                    callback.onExit(0);
                }
                if (executor != null) {
                    executor.shutdown();
                }
            }
        });
    }

    private String processCommand(String command, String args, PrintWriter errorWriter) {
        System.out.println("processCommand() called with command=[" + command + "], args=[" + args + "]");
        System.err.println("processCommand() called with command=[" + command + "], args=[" + args + "] (stderr)");
        
        boolean returnJson = args.contains("-json");
        if (returnJson) {
            args = args.replace("-json", "").trim();
        }

        try {
            String result;
            System.out.println("Processing command switch for: " + command);
            switch (command) {
                case "start":
                    result = scheduler.shellCommandStart(returnJson ? "-json" : "");
                    break;
                case "restart":
                    result = scheduler.shellCommandRestart(returnJson ? "-json" : "");
                    break;
                case "stop":
                    result = scheduler.shellCommandStop(returnJson ? "-json" : "");
                    break;
                case "backup":
                    result = scheduler.shellCommandRunNodeBackup(args + (returnJson ? " -json" : ""));
                    break;
                case "runtask":
                    result = scheduler.shellCommandRuntask(args + (returnJson ? " -json" : ""));
                    break;
                case "status":
                    result = scheduler.shellCommandStatus(returnJson ? "-json" : "");
                    break;
                case "version":
                    result = scheduler.shellCommandVersion(returnJson ? "-json" : "");
                    break;
                case "help":
                    result = getHelpText();
                    break;
                default:
                    System.out.println("Unknown command: " + command + ". Available commands: start, restart, stop, backup, runtask, status, version, help");
                    System.err.println("Unknown command: " + command + " (stderr)");
                    errorWriter.println("Unknown command: " + command + ". Type 'help' for available commands.");
                    errorWriter.flush();
                    return null;
            }
            return result;
        } catch (Exception e) {
            errorWriter.println("Error executing command: " + e.getMessage());
            return null;
        }
    }

    private String getHelpText() {
        return "Available commands:\n" +
               "  start              - Start scheduler\n" +
               "  restart            - Restart scheduler\n" +
               "  stop               - Stop scheduler\n" +
               "  backup <NODE ID>   - Single node backup\n" +
               "  runtask <TASK>     - Run task by name\n" +
               "  status             - Get scheduler status\n" +
               "  version            - Get scheduler version\n" +
               "  help               - Show this help\n" +
               "\n" +
               "Add -json to any command to get JSON output";
    }

    @Override
    public void destroy(ChannelSession channel) throws Exception {
        if (executor != null) {
            executor.shutdown();
        }
    }
}

