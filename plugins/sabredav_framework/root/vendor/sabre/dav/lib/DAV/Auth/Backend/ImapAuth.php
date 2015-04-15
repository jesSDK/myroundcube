<?php
namespace Sabre\DAV\Auth\Backend;

class ImapAuth extends \Sabre\DAV\Auth\Backend\AbstractBasic
{
    private $imap;
    private $pdo;
    private $autoban;
    private $attempts;
    private $table;

    public function __construct($pdo, $imap, $autoban, $attempts, $table, $authenticationSuccessLogFile, $authenticationFailureLogFile)
    {
        $this->imap = $imap;
        $this->pdo = $pdo;
        $this->autoban = $autoban;
        $this->attempts = $attempts;
        $this->table = $table;
        $this->authenticationSuccessLogFile = $authenticationSuccessLogFile;
        $this->authenticationFailureLogFile = $authenticationFailureLogFile;
    }
    
    private function logAuthenticationSuccess($username)
    {
        if (is_string($this->authenticationSuccessLogFile))
        {
            $rip = $_SERVER['REMOTE_ADDR'];
            $timezone = date_default_timezone_get();
            date_default_timezone_set(ini_get('date.timezone'));
            $log = date('Y-m-d H:i:s') . " - Authentication succeeded for user '$username' (RIP: [$rip])." . PHP_EOL;
            file_put_contents($this->authenticationSuccessLogFile, $log, FILE_APPEND);
            date_default_timezone_set($timezone);
        }
    }
    
    private function logAuthenticationFailure($username)
    {
        if (is_string($this->authenticationFailureLogFile))
        {
            $rip = $_SERVER['REMOTE_ADDR'];
            $timezone = date_default_timezone_get();
            date_default_timezone_set(ini_get('date.timezone'));
            $log = date('Y-m-d H:i:s',time()) . " - Authentication failed for user '$username' (RIP: [$rip])." . PHP_EOL;
            file_put_contents($this->authenticationFailureLogFile, $log, FILE_APPEND);
            date_default_timezone_set($timezone);
        }
    }

    protected function validateUserPass($username, $password)
    {
        if(is_numeric($this->autoban) && is_numeric($this->attempts) && is_string($this->table))
        {
            $stmt = $this->pdo->prepare("DELETE FROM " . $this->table . " WHERE ts < ?");
            $stmt->execute(array(date('Y-m-d H:i:s', time() - (($this->autoban + 1) * 60))));
        
            $stmt = $this->pdo->prepare("SELECT * FROM " . $this->table . " WHERE username=? AND ip=?");
            $stmt->execute(array($username, $_SERVER['REMOTE_ADDR']));
        
            $count = 0;
        
            while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
            {
                $count ++;
            }
        
            if($count >= $this->attempts - 1)
            {
                return false;
            }
        }
        
        if (!$this->authenticateUser($username, $password))
        {
            if (is_string($this->table)) {
                $stmt = $this->pdo->prepare("INSERT INTO " . $this->table . " (ip, username, ts) VALUES (?, ?, ?)");
                $stmt->execute(array($_SERVER['REMOTE_ADDR'], $username, date('Y-m-d H:i:s', time())));
            }
            
            throw new \Sabre\DAV\Exception\NotAuthenticated('Username or password does not match');
        }

        return true;
    }

    private function authenticateUser($username, $password)
    {
        try
        {
            if($imap = @imap_open($this->imap, $username, $password, OP_HALFOPEN, 0))
            {
                imap_close($imap);
                $this->logAuthenticationSuccess($username);
                return true;
            }
            else
            {
                $this->logAuthenticationFailure($username);
                return false;
            }
        }
        catch (\Exception $e)
        {
            $this->logAuthenticationFailure($username);
            return false;
        }
    }
}
?>