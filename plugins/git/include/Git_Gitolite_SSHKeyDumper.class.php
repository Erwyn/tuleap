<?php
/**
 * Copyright (c) Enalean, 2012. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once 'common/user/IHaveAnSSHKey.php';
require_once 'Git_Exec.class.php';

class Git_Gitolite_SSHKeyDumper {
    const KEYDIR = 'keydir';

    private $admin_path;
    private $git_exec;

    public function __construct($admin_path, Git_Exec $git_exec) {
        $this->admin_path   = $admin_path;
        $this->git_exec     = $git_exec;
    }

    /**
     * Absolute path to gitolite keydir
     *
     * @return String
     */
    public function getKeyDirPath() {
        return $this->admin_path.'/'.self::KEYDIR;
    }

    /**
     * Return Git_Exec object
     *
     * @return Git_Exec
     */
    public function getGitExec() {
        return $this->git_exec;
    }

    /**
     * Dump ssh keys into gitolite conf
     *
     * @return Boolean
     */
    public function dumpSSHKeys(IHaveAnSSHKey $user) {
        $this->dumpSSHKeysWithoutCommit($user);
        return $this->commitKeyDir('Update '.$user->getUserName().' SSH keys');
    }

    /**
     * Add pending modification to index and commit with message
     *
     * @param String $message
     *
     * @return Boolean
     */
    public function commitKeyDir($message) {
        clearstatcache();
        if (is_dir($this->getKeyDirPath())) {
            $this->git_exec->add($this->getKeyDirPath());
        }
        return $this->git_exec->commit($message);
    }

    /**
     * Dump user SSH key
     *
     * @param IHaveAnSSHKey $user
     *
     * @return boolean
     */
    public function dumpSSHKeysWithoutCommit(IHaveAnSSHKey $user) {
        if (is_dir($this->admin_path)) {
            $this->createKeydir();
            $this->initUserKeys($user);
            return true;
        }
        return false;
    }

    private function initUserKeys(IHaveAnSSHKey $user) {
        $this->dumpKeys($user);
    }

    private function createKeydir() {
        clearstatcache();
        if (!is_dir($this->getKeyDirPath())) {
            if (!mkdir($this->getKeyDirPath())) {
                throw new Exception('Unable to create "'.$this->getKeyDirPath().'" directory in ');
            }
        }
    }

    private function dumpKeys(IHaveAnSSHKey $user) {
        $i = 0;
        foreach ($user->getAuthorizedKeysArray() as $key) {
            $filePath = $this->getKeyDirPath().'/'.$user->getUserName().'@'.$i.'.pub';
            $this->writeKeyIfChanged($filePath, $key);
            $i++;
        }
        $this->removeUserExistingKeys($user, $i);
    }

    private function writeKeyIfChanged($filePath, $key) {
        $changed = true;
        if (is_file($filePath)) {
            $stored_key = file_get_contents($filePath);
            if ($stored_key == $key) {
                $changed = false;
            }
        }
        if ($changed) {
            file_put_contents($filePath, $key);
        }
    }

    /**
     * Remove all pub SSH keys previously associated to a user
     *
     * @param IHaveAnSSHKey $user
     */
    private function removeUserExistingKeys(IHaveAnSSHKey $user, $last_key_id) {
        if (is_dir($this->getKeyDirPath())) {
            $userbase = $user->getUserName().'@';
            foreach (glob($this->getKeyDirPath()."/$userbase*.pub") as $file) {
                if ($this->getKeyNumber($userbase, $file) >= $last_key_id) {
                    $this->git_exec->rm($file);
                }
            }
        }
    }

    private function getKeyNumber($userbase, $file) {
        $matches = array();
        if (preg_match('%^'.$this->getKeyDirPath().'/'.$userbase.'([0-9]+).pub$%', $file, $matches)) {
            return intval($matches[1]);
        }
        return -1;
    }
}

?>
