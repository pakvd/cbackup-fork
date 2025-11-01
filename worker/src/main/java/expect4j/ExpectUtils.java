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

import org.apache.commons.net.telnet.TelnetClient;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;

/**
 * Utility class for creating Expect4j instances with different connection types
 */
public class ExpectUtils {

    /**
     * Create an Expect4j instance using Telnet connection
     *
     * @param host Hostname or IP address
     * @param port Port number
     * @return Expect4j instance
     * @throws IOException if connection fails
     */
    public static Expect4j telnet(String host, int port) throws IOException {
        TelnetClient telnetClient = new TelnetClient();
        telnetClient.connect(host, port);
        
        InputStream inputStream = telnetClient.getInputStream();
        OutputStream outputStream = telnetClient.getOutputStream();
        
        return new Expect4j(inputStream, outputStream);
    }
}

