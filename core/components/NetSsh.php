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

namespace app\components;

use phpseclib\File\ANSI;
use phpseclib\Net\SSH2;
use yii\helpers\Json;

/**
 * @package app\components
 */
class NetSsh
{

    /**
     * @var \phpseclib\Net\SSH2
     */
    private $ssh;

    /**
     * @var string
     */
    private $ip;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var int
     */
    private $port;

    /**
     * @var int
     */
    private $timeout = 5;

    /**
     * NetSsh constructor.
     */
    public function __construct()
    {
        // In Docker environment, use service name 'worker' instead of '127.0.0.1'
        $defaultHost = (getenv('DOCKER_CONTAINER') === 'true' || getenv('container') === 'docker') ? 'worker' : '127.0.0.1';
        $this->ip       = \Y::param('javaHost', $defaultHost);
        $this->port     = \Y::param('javaSchedulerPort');
        $this->username = \Y::param('javaSchedulerUsername');
        $this->password = \Y::param('javaSchedulerPassword');
    }

    /**
     * Init SSH wrapper
     *
     * @param  array $options
     * @return $this
     * @throws \Exception
     */
    public function init(array $options = [])
    {
        /** Set custom init options */
        array_walk($options, function($value, $option) { $this->$option = $value; });

        /** Validate connection parameters */
        if (empty($this->ip)) {
            // In Docker environment, use service name 'worker' instead of '127.0.0.1'
            $this->ip = (getenv('DOCKER_CONTAINER') === 'true' || getenv('container') === 'docker') ? 'worker' : '127.0.0.1';
        }
        if (empty($this->port) || $this->port === null) {
            throw new \Exception("Java scheduler port (javaSchedulerPort) is not configured. Please set it in System > Configuration.");
        }
        if (empty($this->username)) {
            throw new \Exception("Java scheduler username (javaSchedulerUsername) is not configured. Please set it in System > Configuration.");
        }
        if (empty($this->password)) {
            throw new \Exception("Java scheduler password (javaSchedulerPassword) is not configured. Please set it in System > Configuration.");
        }

        /** Connect to device */
        $this->ssh = new SSH2($this->ip, $this->port, $this->timeout);

        /** Note: phpseclib 2.0.9 by default supports ssh-rsa which matches SSHD server
         *  If "No compatible server host key algorithms found" error occurs,
         *  it's likely due to SSHD server configuration, not phpseclib client
         */

        /** Show exception if can not login */
        if (!$this->ssh->login($this->username, $this->password)) {
            throw new \Exception("Authentication failed. Host:{$this->ip}:{$this->port}. Check SSH credentials");
        }

        return $this;
    }

    /**
     * Execute command using exec command
     *
     * @param  string $command
     * @return string
     */
    public function exec(string $command)
    {
        return trim($this->ssh->exec($command));
    }

    /**
     * Execute command by parsing terminal output
     *
     * @param  string $command
     * @return array
     * @throws \Exception
     */
    public function schedulerExec(string $command):array
    {
        /** Wait for initial prompt - increase timeout for first read */
        $this->ssh->setTimeout(15);
        
        /** Read initial prompt - may timeout if server doesn't send prompt immediately */
        try {
            $this->ssh->read('/.*[>]\s$/', $this->ssh::READ_REGEX);
        } catch (\Exception $e) {
            // If read fails, try to continue anyway - server might have sent prompt already
            error_log("Warning: Could not read initial prompt: " . $e->getMessage());
        }
        
        /** Execute command */
        $this->ssh->write("{$command}\n");
        $this->ssh->setTimeout(30);

        /** Read command output */
        try {
            // Read output until we see the prompt again
            // The prompt format is "cbackup> " (with space after >)
            // Try multiple patterns to match the prompt
            $patterns = [
                '/cbackup>\s*$/m',           // Exact match with optional spaces
                '/cbackup>\s*$/s',           // With dotall flag
                '/.*cbackup>\s*$/m',         // Match anything before prompt
                '/cbackup>\s*$/',             // Simple match
            ];
            
            $output = null;
            $lastException = null;
            
            foreach ($patterns as $pattern) {
                try {
                    $output = $this->ssh->read($pattern, $this->ssh::READ_REGEX);
                    break;
                } catch (\Exception $e) {
                    $lastException = $e;
                    continue;
                }
            }
            
            if ($output === null) {
                throw $lastException ?: new \Exception("Could not read output with any pattern");
            }
            
            // Remove the prompt from the end of output
            $output = preg_replace('/cbackup>\s*$/m', '', $output);
            $output = trim($output);
            
            // Log for debugging
            if (empty($output)) {
                error_log("Warning: Empty output from SSH command: " . $command);
            } else {
                error_log("SSH command output length: " . strlen($output) . " chars");
            }
        } catch (\Exception $e) {
            throw new \Exception("Failed to read command output: " . $e->getMessage());
        }

        /** Show console output if error occurs */
        if (!preg_match('/{.*}/i', $output, $json)) {
            $ansi = new ANSI();
            $ansi->appendString($output);
            $prep_output = htmlspecialchars_decode(strip_tags($ansi->getScreen()));
            $error_array = explode("\n", $prep_output);
            $error_text  = (array_key_exists(1, $error_array) && !empty($error_array[1])) ? $error_array[1] : $prep_output;
            throw new \Exception($error_text);
        }

        return Json::decode($json[0]);
    }

}
