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
package expect4j;

import net.sf.expectit.Expect;
import net.sf.expectit.ExpectBuilder;
import net.sf.expectit.Result;
import net.sf.expectit.matcher.Matcher;
import net.sf.expectit.matcher.Matchers;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.nio.charset.Charset;
import java.util.concurrent.TimeUnit;
import java.util.regex.Pattern;

/**
 * Expect4j compatibility wrapper using expectit-core
 * This class provides the same API as Expect4j but uses expectit-core internally
 */
public class Expect4j {
    private Expect expect;
    private LastState lastState;
    private int defaultTimeout = 30000; // 30 seconds default
    private String lastBuffer = "";
    private InputStream inputStream;
    private OutputStream outputStream;

    /**
     * Constructor
     *
     * @param inputStream  Input stream
     * @param outputStream Output stream
     */
    public Expect4j(InputStream inputStream, OutputStream outputStream) throws IOException {
        this.inputStream = inputStream;
        this.outputStream = outputStream;
        this.expect = new ExpectBuilder()
                .withInputs(inputStream)
                .withOutput(outputStream)
                .withCharset(Charset.defaultCharset())
                .withTimeout(defaultTimeout, TimeUnit.MILLISECONDS)
                .build();
        this.lastState = new LastState();
    }

    /**
     * Expect a pattern
     *
     * @param pattern Pattern to expect (regex string)
     * @return int - COMMAND_EXECUTION_SUCCESS_OPCODE (-2) on success, otherwise other value
     */
    public int expect(String pattern) {
        try {
            if (pattern == null || pattern.isEmpty()) {
                // Just wait for any output or timeout
                try {
                    Result result = expect.expect(Matchers.anyString());
                    lastBuffer = result.getInput();
                    lastState.setBuffer(lastBuffer);
                    return COMMAND_EXECUTION_SUCCESS_OPCODE;
                } catch (IOException e) {
                    return -1; // Timeout or failure
                }
            }

            Matcher<Result> matcher = Matchers.regexp(Pattern.compile(pattern));
            Result result = expect.expect(matcher);
            
            // Get the full input - expectit returns the complete input
            String input = result.getInput();
            
            // Accumulate to existing buffer
            if (input != null && !input.isEmpty()) {
                if (!lastBuffer.isEmpty() && !input.startsWith(lastBuffer)) {
                    // New data, append it
                    lastBuffer += input.substring(lastBuffer.length());
                } else if (lastBuffer.isEmpty() || input.length() > lastBuffer.length()) {
                    // New or longer data
                    lastBuffer = input;
                }
            }
            
            lastState.setBuffer(lastBuffer);
            return COMMAND_EXECUTION_SUCCESS_OPCODE;
        } catch (IOException e) {
            return -1; // Failure
        }
    }

    /**
     * Send text
     *
     * @param text Text to send
     */
    public void send(String text) {
        try {
            expect.send(text);
            // Note: sent text is not added to buffer, only received output is
        } catch (IOException e) {
            throw new RuntimeException("Failed to send text", e);
        }
    }

    /**
     * Set default timeout
     *
     * @param timeout Timeout in milliseconds
     */
    public void setDefaultTimeout(int timeout) {
        this.defaultTimeout = timeout;
        // Rebuild expectit with new timeout
        try {
            if (expect != null) {
                expect.close();
            }
        } catch (IOException e) {
            // Ignore close errors
        }
        
        try {
            this.expect = new ExpectBuilder()
                    .withInputs(inputStream)
                    .withOutput(outputStream)
                    .withCharset(Charset.defaultCharset())
                    .withTimeout(timeout, TimeUnit.MILLISECONDS)
                    .build();
        } catch (IOException e) {
            throw new RuntimeException("Failed to rebuild expect with new timeout", e);
        }
    }

    /**
     * Get last state
     *
     * @return LastState object
     */
    public LastState getLastState() {
        return lastState;
    }

    /**
     * Close the expect connection
     */
    public void close() throws IOException {
        if (expect != null) {
            expect.close();
        }
    }

    /**
     * Last state class for buffer management
     */
    public class LastState {
        /**
         * Get buffer
         *
         * @return String buffer
         */
        public String getBuffer() {
            return lastBuffer;
        }

        /**
         * Set buffer
         *
         * @param buffer Buffer content
         */
        public void setBuffer(String buffer) {
            lastBuffer = buffer != null ? buffer : "";
        }
    }

    /**
     * Command execution success opcode
     */
    public static final int COMMAND_EXECUTION_SUCCESS_OPCODE = -2;
}

