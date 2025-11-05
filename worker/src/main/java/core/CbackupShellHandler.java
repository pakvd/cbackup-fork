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
        executor = Executors.newSingleThreadExecutor();
        executor.submit(() -> {
            try (BufferedReader reader = new BufferedReader(new InputStreamReader(in));
                 PrintWriter writer = new PrintWriter(new OutputStreamWriter(out), true);
                 PrintWriter errorWriter = new PrintWriter(new OutputStreamWriter(err), true)) {
                
                writer.println("cBackup Shell - Type 'help' for available commands");
                writer.print("cbackup> ");

                String line;
                while ((line = reader.readLine()) != null) {
                    if (line.trim().isEmpty()) {
                        writer.print("cbackup> ");
                        continue;
                    }

                    String[] parts = line.trim().split("\\s+");
                    String command = parts[0].toLowerCase();
                    String args = parts.length > 1 ? line.substring(parts[0].length()).trim() : "";

                    String result = processCommand(command, args, errorWriter);
                    if (result != null) {
                        writer.println(result);
                    }
                    writer.print("cbackup> ");
                }
            } catch (IOException e) {
                // Connection closed
            } finally {
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
        boolean returnJson = args.contains("-json");
        if (returnJson) {
            args = args.replace("-json", "").trim();
        }

        try {
            String result;
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
                    errorWriter.println("Unknown command: " + command + ". Type 'help' for available commands.");
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

