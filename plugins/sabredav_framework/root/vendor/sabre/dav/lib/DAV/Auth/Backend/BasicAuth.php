<?php
namespace Sabre\DAV\Auth\Backend;

class BasicAuth extends \Sabre\DAV\Auth\Backend\AbstractBasic
{
    private $pdo;
    private $table;
    private $realm;

    public function __construct($pdo, $table, $realm)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->realm = $realm;
    }

    protected function validateUserPass($username, $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM " . $this->table . " WHERE username=?");
        $stmt->execute(array($username));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row) && isset($row['digesta1'])) {
             if ($row['digesta1'] === md5($username . ':' . $this->realm . ':' . $password)) {
                 return true;
             } else {
                 return false;
             }
         } else {
             return false;
         }
    }
}
?>