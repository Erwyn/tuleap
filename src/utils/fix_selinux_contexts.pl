#!/usr/bin/perl
#
# CodeX: Breaking Down the Barriers to Source Code Sharing inside Xerox
# Copyright (c) Xerox Corporation, CodeX, 2001-2006. All Rights Reserved
# This file is licensed under the GNU General Public License
#
# 
#
# Purpose:
#    Automatically fix SELinux contexts to allow proper access to Apache and MySAL
#

require("include.pl");  # Include all the predefined functions and variables
$CHCON='/usr/bin/chcon';
$context="root:object_r:httpd_sys_content_t";

if (( ! -e $CHCON ) || ( ! -e "/etc/selinux/config" ) || ( `grep -i '^SELINUX=disabled' /etc/selinux/config`)) {
   # SELinux not installed or disabled
   $CHCON="echo ignored: chcon";
}

# /usr/share/codex -> CodeX main Web tree, documentation, plugins, etc.
`$CHCON -R -h $context $codex_dir`;

# /etc/codex -> for licence, site-content...
`$CHCON -R -h $context $sys_custom_dir`;

# /var/lib/codex
`$CHCON -R -h $context $sys_data_dir`;

# FTP directories
`$CHCON -R -h system_u:object_r:public_content_t $sys_data_dir/ftp`;
`$CHCON -R -h system_u:object_r:public_content_rw_t  $sys_data_dir/ftp/incoming`;
# Releases must be accessed from httpd
`$CHCON -R -h $context $sys_data_dir/ftp/codex`;

# Allow anonymous FTP writes
`setsebool -P allow_ftpd_anon_write=1`;
# Allow access to user's home with FTP
`setsebool -P ftp_home_dir 1`

# /home/codexadm. Apache needs access to '.subversion' (Server update plugin), '.cvs' (Passerelle plugin)
`$CHCON -R -h $context /home/$sys_http_user`;

# /home/groups -> project web sites
`$CHCON -R -h $context $grpdir_prefix`;

`$CHCON -h $context /svnroot`;
`$CHCON -h system_u:object_r:cvs_data_t /cvsroot`;
`$CHCON -R -h system_u:object_r:cvs_data_t /cvsroot/`;
