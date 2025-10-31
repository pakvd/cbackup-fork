<?php
/**
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

namespace app\helpers;


/**
 * @package app\helpers
 */
class SystemHelper
{

    /**
     * @param  string $cmd
     * @return object {'stdin' => string, 'stdout' => string, 'stderr' => string, 'exitcode' => int}
     */
    public static function exec( $cmd )
    {

        $pipes  = [];
        $desc   = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $proc   = @proc_open($cmd, $desc, $pipes);

        if (!is_resource($proc)) {
            return (object)[
                'stdin'    => '',
                'stdout'   => '',
                'stderr'   => 'Failed to open process',
                'exitcode' => -1
            ];
        }

        // Close stdin pipe first (we write to it, not read from it)
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
            unset($pipes[0]);
        }

        // Read stdout and stderr
        $stdout = '';
        $stderr = '';
        
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            $stdout = @stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            unset($pipes[1]);
        }
        
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            $stderr = @stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            unset($pipes[2]);
        }

        // Get exit code and close process
        $exitcode = proc_close($proc);

        $result = [
            'stdin'    => '',
            'stdout'   => trim($stdout ?: ''),
            'stderr'   => trim($stderr ?: ''),
            'exitcode' => $exitcode
        ];

        return (object)$result;

    }


    /**
     * @return string
     */
    public static function generateToken()
    {
        return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),                       // 32 bits for "time_low"
            mt_rand( 0, 0xffff ),                                             // 16 bits for "time_mid"
            mt_rand( 0, 0x0fff ) | 0x4000,                                    // 16 bits for "time_hi_and_version"
            mt_rand( 0, 0x3fff ) | 0x8000,                                    //  8 bits for "clk_seq_hi_res" + 8 bits for "clk_seq_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )  // 48 bits for "node"
        );
    }

}
