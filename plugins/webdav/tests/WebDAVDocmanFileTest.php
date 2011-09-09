<?php
/**
 * Copyright (c) STMicroelectronics, 2010. All Rights Reserved.
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

require_once (dirname(__FILE__).'/../../../src/common/language/BaseLanguage.class.php');
Mock::generate('BaseLanguage');
require_once (dirname(__FILE__).'/../../../src/common/user/User.class.php');
Mock::generate('User');
require_once (dirname(__FILE__).'/../../../src/common/project/Project.class.php');
Mock::generate('Project');
require_once ('requirements.php');
require_once (dirname(__FILE__).'/../include/WebDAVUtils.class.php');
Mock::generate('WebDAVUtils');
require_once (dirname(__FILE__).'/../../docman/include/Docman_Version.class.php');
Mock::generate('Docman_Version');
require_once (dirname(__FILE__).'/../../docman/include/Docman_VersionFactory.class.php');
Mock::generate('Docman_VersionFactory');
require_once (dirname(__FILE__).'/../../docman/include/Docman_File.class.php');
Mock::generate('Docman_File');
require_once (dirname(__FILE__).'/../../docman/include/Docman_Item.class.php');
Mock::generate('Docman_Item');
require_once (dirname(__FILE__).'/../../docman/include/Docman_ItemFactory.class.php');
Mock::generate('Docman_ItemFactory');
require_once (dirname(__FILE__).'/../../docman/include/Docman_PermissionsManager.class.php');
Mock::generate('Docman_PermissionsManager');
require_once (dirname(__FILE__).'/../../docman/include/Docman_FileStorage.class.php');
Mock::generate('Docman_FileStorage');
require_once (dirname(__FILE__).'/../include/FS/WebDAVDocmanFile.class.php');
Mock::generatePartial(
    'WebDAVDocmanFile',
    'WebDAVDocmanFileTestVersion',
array('getSize', 'getMaxFileSize', 'getItem', 'getUser', 'logDownload', 'download', 'getUtils', 'getProject')
);

/**
 * This is the unit test of WebDAVDocmanFile
 */
class WebDAVDocmanFileTest extends UnitTestCase {

    /**
     * Constructor of the test. Can be ommitted.
     * Usefull to set the name of the test
     */
    function WebDAVDocmanFileTest($name = 'WebDAVDocmanFileTest') {
        $this->UnitTestCase($name);
    }

    function setUp() {
        $GLOBALS['Language'] = new MockBaseLanguage($this);
    }

    function tearDown() {
        unset($GLOBALS['Language']);
    }

    /**
     * Test when the file doesn't exist on the filesystem
     */
    function testGetNotFound() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion($this);

        $version = new MockDocman_Version();
        $version->setReturnValue('getPath', dirname(__FILE__).'/_fixtures/nonExistant');
        $item = new MockDocman_File();
        $item->setReturnValue('getCurrentVersion', $version);
        $webDAVDocmanFile->setReturnValue('getItem', $item);

        $this->expectException('Sabre_DAV_Exception_FileNotFound');
        $webDAVDocmanFile->get();
    }

    /**
     * Test when the file is too big
     */
    function testGetBigFile() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion($this);

        $webDAVDocmanFile->setReturnValue('getSize', 2);
        $webDAVDocmanFile->setReturnValue('getMaxFileSize', 1);

        $version = new MockDocman_Version();
        $version->setReturnValue('getPath', dirname(__FILE__).'/_fixtures/test.txt');
        $item = new MockDocman_File();
        $item->setReturnValue('getCurrentVersion', $version);
        $webDAVDocmanFile->setReturnValue('getItem', $item);

        $this->expectException('Sabre_DAV_Exception_RequestedRangeNotSatisfiable');
        $webDAVDocmanFile->get();
    }

    /**
     * Test when the file download succeede
     */
    function testGetSucceede() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion($this);

        $webDAVDocmanFile->setReturnValue('getSize', 1);
        $webDAVDocmanFile->setReturnValue('getMaxFileSize', 1);

        $version = new MockDocman_Version();
        $version->setReturnValue('getPath', dirname(__FILE__).'/_fixtures/test.txt');
        $item = new MockDocman_File();
        $item->setReturnValue('getCurrentVersion', $version);
        $webDAVDocmanFile->setReturnValue('getItem', $item);

        $this->assertNoErrors();
        $webDAVDocmanFile->get();
    }

    function testPutNoWriteEnabled() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion();
        $utils = new MockWebDAVUtils();
        $utils->setReturnValue('isWriteEnabled', false);
        $webDAVDocmanFile->setReturnValue('getUtils', $utils);

        $this->expectException('Sabre_DAV_Exception_Forbidden');
        $data = fopen(dirname(__FILE__).'/_fixtures/test.txt', 'r');
        $webDAVDocmanFile->put($data);
    }

    function testPutNoPermissions() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion();
        $item = new MockDocman_Item();
        $webDAVDocmanFile->setReturnValue('getItem', $item);
        $utils = new MockWebDAVUtils();
        $dpm = new MockDocman_PermissionsManager();
        $dpm->setReturnValue('userCanWrite', false);
        $utils->setReturnValue('getDocmanPermissionsManager', $dpm);
        $utils->setReturnValue('isWriteEnabled', true);
        $webDAVDocmanFile->setReturnValue('getUtils', $utils);

        $this->expectException('Sabre_DAV_Exception_Forbidden');
        $data = fopen(dirname(__FILE__).'/_fixtures/test.txt', 'r');
        $webDAVDocmanFile->put($data);
    }

    function testPutNotStored() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion();
        $item = new MockDocman_Item();
        $webDAVDocmanFile->setReturnValue('getItem', $item);
        $project = new MockProject();
        $webDAVDocmanFile->setReturnValue('getProject', $project);
        $utils = new MockWebDAVUtils();
        $dpm = new MockDocman_PermissionsManager();
        $dpm->setReturnValue('userCanWrite', true);
        $utils->setReturnValue('getDocmanPermissionsManager', $dpm);
        $vf = new MockDocman_VersionFactory();
        $utils->setReturnValue('getVersionFactory', $vf);
        $utils->setReturnValue('isWriteEnabled', true);
        $fs = new MockDocman_FileStorage();
        $fs->setReturnValue('store', null);
        $utils->setReturnValue('getFileStorage', $fs);
        $webDAVDocmanFile->setReturnValue('getUtils', $utils);

        $this->expectException('WebDAVExceptionServerError');
        $data = fopen(dirname(__FILE__).'/_fixtures/test.txt', 'r');
        $webDAVDocmanFile->put($data);
    }

    function testPutBigFile() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion();
        $item = new MockDocman_Item();
        $webDAVDocmanFile->setReturnValue('getItem', $item);
        $project = new MockProject();
        $webDAVDocmanFile->setReturnValue('getProject', $project);
        $user = new MockUser();
        $webDAVDocmanFile->setReturnValue('getUser', $user);
        $utils = new MockWebDAVUtils();
        $dpm = new MockDocman_PermissionsManager();
        $dpm->setReturnValue('userCanWrite', true);
        $utils->setReturnValue('getDocmanPermissionsManager', $dpm);
        $vf = new MockDocman_VersionFactory();
        $vf->setReturnValue('create', null);
        $utils->setReturnValue('getVersionFactory', $vf);
        $utils->setReturnValue('isWriteEnabled', true);
        $fs = new MockDocman_FileStorage();
        $fs->setReturnValue('store', dirname(__FILE__).'/_fixtures/');
        $utils->setReturnValue('getFileStorage', $fs);
        $webDAVDocmanFile->setReturnValue('getUtils', $utils);
        $webDAVDocmanFile->setReturnValue('getMaxFileSize', 4095);

        $this->expectException('Sabre_DAV_Exception_RequestedRangeNotSatisfiable');
        $data = fopen(dirname(__FILE__).'/_fixtures/test.txt', 'r');
        $webDAVDocmanFile->put($data);
    }

    function testPutFailToCreate() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion();
        $item = new MockDocman_Item();
        $webDAVDocmanFile->setReturnValue('getItem', $item);
        $project = new MockProject();
        $webDAVDocmanFile->setReturnValue('getProject', $project);
        $user = new MockUser();
        $webDAVDocmanFile->setReturnValue('getUser', $user);
        $utils = new MockWebDAVUtils();
        $dpm = new MockDocman_PermissionsManager();
        $dpm->setReturnValue('userCanWrite', true);
        $utils->setReturnValue('getDocmanPermissionsManager', $dpm);
        $vf = new MockDocman_VersionFactory();
        $vf->setReturnValue('create', null);
        $utils->setReturnValue('getVersionFactory', $vf);
        $utils->setReturnValue('isWriteEnabled', true);
        $fs = new MockDocman_FileStorage();
        $fs->setReturnValue('store', dirname(__FILE__).'/_fixtures/');
        $utils->setReturnValue('getFileStorage', $fs);
        $webDAVDocmanFile->setReturnValue('getUtils', $utils);
        $webDAVDocmanFile->setReturnValue('getMaxFileSize', 4096);

        $this->expectException('WebDAVExceptionServerError');
        $data = fopen(dirname(__FILE__).'/_fixtures/test.txt', 'r');
        $webDAVDocmanFile->put($data);
    }

    function testPutSucceede() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion();
        $item = new MockDocman_Item();
        $webDAVDocmanFile->setReturnValue('getItem', $item);
        $project = new MockProject();
        $webDAVDocmanFile->setReturnValue('getProject', $project);
        $user = new MockUser();
        $webDAVDocmanFile->setReturnValue('getUser', $user);
        $utils = new MockWebDAVUtils();
        $dpm = new MockDocman_PermissionsManager();
        $dpm->setReturnValue('userCanWrite', true);
        $utils->setReturnValue('getDocmanPermissionsManager', $dpm);
        $vf = new MockDocman_VersionFactory();
        $vf->setReturnValue('create', true);
        $utils->setReturnValue('getVersionFactory', $vf);
        $utils->setReturnValue('isWriteEnabled', true);
        $fs = new MockDocman_FileStorage();
        $fs->setReturnValue('store', dirname(__FILE__).'/_fixtures/');
        $utils->setReturnValue('getFileStorage', $fs);
        $webDAVDocmanFile->setReturnValue('getUtils', $utils);
        $webDAVDocmanFile->setReturnValue('getMaxFileSize', 4096);

        $this->assertNoErrors();
        $data = fopen(dirname(__FILE__).'/_fixtures/test.txt', 'r');
        $webDAVDocmanFile->put($data);
    }

    function testSetNameFile() {
        $webDAVDocmanFile = new WebDAVDocmanFileTestVersion();
        $item = new Docman_File();
        $webDAVDocmanFile->setReturnValue('getItem', $item);
        $this->expectException('Sabre_DAV_Exception_MethodNotAllowed');
        $webDAVDocmanFile->setName('newName');
    }

    function testSetNameEmbeddedFile() {
        $webDAVDocmanFile = new WebDAVDocmanDocumentTestVersion();

        $utils = new MockWebDAVUtils();
        $utils->setReturnValue('isWriteEnabled', true);
        $utils->expectOnce('processDocmanRequest');
        $webDAVDocmanFile->setReturnValue('getUtils', $utils);
        
        $project = new MockProject();
        $webDAVDocmanFile->setReturnValue('getProject', $project);
        
        $item = new MockDocman_Item();
        $webDAVDocmanFile->setReturnValue('getItem', $item);
        
        $this->assertNoErrors();
        $webDAVDocmanFile->setName('newName');
    }

}

?>