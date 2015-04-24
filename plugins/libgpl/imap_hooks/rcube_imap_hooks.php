<?php
class rcube_imap_hooks extends rcube_imap
{
    public function __construct()
    {
        $this->plugins = rcube::get_instance()->plugins;
        $this->conn = new rcube_imap_hooks_generic();

        // Set namespace and delimiter from session,
        // so some methods would work before connection
        if (isset($_SESSION['imap_namespace'])) {
            $this->namespace = $_SESSION['imap_namespace'];
        }
        if (isset($_SESSION['imap_delimiter'])) {
            $this->delimiter = $_SESSION['imap_delimiter'];
        }
    }
}
?>