<?php
class rcube_imap_hooks_generic extends rcube_imap_generic
{
    function execute($command, $arguments=array(), $options=0)
    {
        $plugin = rcube::get_instance()->plugins->exec_hook("storage_execute_before",
            array(
                'capability' => $this->capability,
                'command' => $command,
                'arguments' => $arguments,
                'options' => $options,
                'error' => $this->error,
                'errornum' => $this->errornum
            )
        );
        $this->error = $plugin['error'];
        $this->errornum = $plugin['errornum'];
        $command = $plugin['command'];
        $arguments = $plugin['arguments'];
        $options = $plugin['options'];

        if (!$plugin['abort']) {
            $tag      = $this->nextTag();
            $query    = $tag . ' ' . $command;
            $noresp   = ($options & self::COMMAND_NORESPONSE);
            $response = $noresp ? null : '';

            if (!empty($arguments)) {
                foreach ($arguments as $arg) {
                    $query .= ' ' . self::r_implode($arg);
                }
            }

            // Send command
            if (!$this->putLineC($query)) {
                $this->setError(self::ERROR_COMMAND, "Unable to send command: $query");
                return $noresp ? self::ERROR_COMMAND : array(self::ERROR_COMMAND, '');
            }

            // Parse response
            do {
                $line = $this->readLine(4096);
                if ($response !== null) {
                    $response .= $line;
                }
            } while (!$this->startsWith($line, $tag . ' ', true, true));

            $code = $this->parseResult($line, $command . ': ');

            // Remove last line from response
            if ($response) {
                $line_len = min(strlen($response), strlen($line) + 2);
                $response = substr($response, 0, -$line_len);
            }

            // optional CAPABILITY response
            if (($options & self::COMMAND_CAPABILITY) && $code == self::ERROR_OK
                && preg_match('/\[CAPABILITY ([^]]+)\]/i', $line, $matches)
            ) {
                $this->parseCapability($matches[1], true);
            }

            // return last line only (without command tag, result and response code)
            if ($line && ($options & self::COMMAND_LASTLINE)) {
                $response = preg_replace("/^$tag (OK|NO|BAD|BYE|PREAUTH)?\s*(\[[a-z-]+\])?\s*/i", '', trim($line));
            }

        }

        $plugin = rcube::get_instance()->plugins->exec_hook("storage_execute_after",
            array(
                'capability' => $this->capability,
                'command' => $command,
                'arguments' => $arguments,
                'options' => $options,
                'code' => $plugin['code'] ? $plugin['code'] : $code,
                'response' => $plugin['response'] ? $plugin['response'] : $response,
                'noresponse' => $noresp,
                'error' => $this->error,
                'errornum' => $this->errornum
            )
        );
        $this->error = $plugin['error'];
        $this->errornum = $plugin['errornum'];
        
        return $plugin['noresponse'] ? $plugin['code'] : array($plugin['code'], $plugin['response']);
    }
}
?>