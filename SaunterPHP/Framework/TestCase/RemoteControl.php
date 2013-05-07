<?php
/**
 * @package SaunterPHP
 * @subpackage Framework_TestCase
 */
namespace RemoteControl;

require_once 'SaunterPHP/Framework/Bindings/SaunterRemoteControl.php';
require_once 'SaunterPHP/Framework/SuiteIdentifier.php';
require_once 'PHPUnit/Framework/TestCase.php';
require_once 'Log.php';
require_once 'SaunterPHP/Framework/Exception.php';

abstract class SaunterPHP_Framework_SaunterTestCase extends \PHPUnit_Framework_TestCase {
    static public $log;
    static public $verificationErrors;
    static public $selenium;

    public function setUp() {
        self::$verificationErrors = array();
        self::$log = \Log::singleton('file', $GLOBALS['settings']['logname'], $this->getName());

        if ($GLOBALS['settings']['sauce.ondemand']) {
            $server_host = $GLOBALS['saucelabs']['username'] . ":" . $GLOBALS['saucelabs']['key'] . "@ondemand.saucelabs.com";

            $profile_path = null;
            if (array_key_exists('profile-' . strtolower(PHP_OS), $GLOBALS['settings'])) {
                $profile_path = $GLOBALS['settings']['saunter.base'] . DIRECTORY_SEPARATOR . 'support/profiles/' . $GLOBALS['saucelabs']['profile-' . strtolower(PHP_OS)];
            } elseif (array_key_exists('profile', $GLOBALS['settings'])) {
                $profile_path = $GLOBALS['settings']['saunter.base'] . DIRECTORY_SEPARATOR . 'support/profiles/' . $GLOBALS['settings']['profile'];
            }
            if ($profile_path) {
                if (is_dir($profile_path)) {
                    $hosted_path = $GLOBALS['settings']['fileserver'] . '/profiles/' . basename($profile_path) . '.zip';

                    $zip = new \ZipArchive();

                    if(($zip->open($profile_path . '.zip', \ZipArchive::OVERWRITE)) !== true) {
                        throw new \SaunterPHP_Framework_Exception("Unable to create profile zip ${profile_path}");
                    }

                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($profile_path, $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS));
                    foreach ($iterator as $key=>$value) {
                        $zip->addFile($key, substr($key, strlen($profile_path) + 1)) or die ("ERROR: Could not add file: $key");
                    }

                    $zip->close();

                    $decoded = json_decode($GLOBALS['settings']['browser'], $assoc = true);
                    $decoded['firefox-profile-url'] = $hosted_path;
                    $GLOBALS['settings']['browser'] = json_encode($decoded);
                } else {
                    throw new \SaunterPHP_Framework_Exception("Profile directory not found at ${profile_path}");
                }
            }
        } else {
            $server_host = $GLOBALS['settings']['seleniumserver'];
        }
        $server_port = $GLOBALS['settings']['seleniumport'];
        $this->driver = new \SaunterPHP_Framework_Bindings_SaunterRemoteControl($GLOBALS['settings']['browser'], $GLOBALS['settings']['webserver'], $server_host, $server_port);
        self::$selenium = $this->driver;

        $this->driver->start();
        $this->driver->windowMaximize();
        
        $this->sessionId = $this->driver->getEval("selenium.sessionId");
    }

    // fired after the test run but before teardown
    public function assertPostConditions() {
        $this->assertEmpty(self::$verificationErrors, implode("\n", self::$verificationErrors));
    }
  
    public function tearDown() { }
    
    /**
     * Verifies that the requested cookie has been set
     *
     * @param string $want
     * @access public
     * @return void
     */
    public function verifyCookiePresent($want) {
        try {
            $this->assertTrue($this->driver->isCookiePresent($want),  $want . ' cookie is not present.');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }

    public function verifyCookieNotPresent($want)
    {
      try {
          $this->assertTrue(!$this->driver->isCookiePresent($want), $want . ' cookie is present.');
      } catch (\PHPUnit_Framework_AssertionFailedError $e) {
          array_push(self::$verificationErrors, $e->toString());
      }
    }

    public function verifyElementAvailable($element) {
        try {
            $this->assertTrue($this->driver->isElementPresent($element), $element . ' element is not present.');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
        if ($this->driver->isElementPresent($element)) {
            try {
                $this->assertTrue($this->driver->isVisible($element), $element . ' element is not available.');
            } catch (\PHPUnit_Framework_AssertionFailedError $e) {
                array_push(self::$verificationErrors, $e->toString());
            }
        }
    }

    public function verifyElementPresent($element) {
        try {
            $this->assertTrue($this->driver->isElementPresent($element), $element . ' element is not present.');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }
    
    public function verifyElementNotPresent($element) {
        try {
            $this->assertTrue(!$this->driver->isElementPresent($element), $element . ' element is present.');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }
    
    public function verifyEquals($want, $got) {
        try {
            $this->assertEquals($want, $got);
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }

    public function verifyFalse($condition, $message = "") {
        try {
            $this->assertFalse($condition);
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            if ($message) {
                array_push(self::$verificationErrors, $message);
            } else {
                array_push(self::$verificationErrors, $e->toString());
            }
        }
    }
    
    public function verifyLocation($relativeURL) {
        try {
            $this->assertEquals($GLOBALS['settings']['webserver'] . $relativeURL, $this->driver->getLocation(),  "URLs don't match with, " . $relativeURL);
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }

    public function verifyNotEquals($want, $got) {
        try {
            $this->assertNotEquals($want, $got);
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }
    
    public function verifyNotLocation($relativeURL)
    {
      try {
          $this->assertNotEquals($GLOBALS['settings']['webserver'] . $relativeURL, $this->driver->getLocation(), "URLs still match with, " . $relativeURL);
      } catch (\PHPUnit_Framework_AssertionFailedError $e) {
          array_push(self::$verificationErrors, $e->toString());
      }
    }
    
    public function verifyNotTextRegEx($element,$pattern) {
        try {
            $this->assertTrue(!(bool)preg_match($pattern,$this->driver->getText($element)));
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }

    public function verifyTextPresent($want) {
        try {
            $this->assertTrue($this->driver->isTextPresent($want), $want . ' Text is not present.');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }

    public function verifyTextNotPresent($want) {
        try {
            $this->assertTrue(!$this->driver->isTextPresent($want), $want . ' Text is present.');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }

    public function verifyTextRegEx($element,$pattern) {
        try {
            $this->assertTrue((bool)preg_match($pattern,$this->driver->getText($element)));
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            array_push(self::$verificationErrors, $e->toString());
        }
    }
    
    public function verifyTrue($condition, $message = "") {
        try {
            $this->assertTrue($condition);
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            if ($message) {
                array_push(self::$verificationErrors, $message);
            } else {
                array_push(self::$verificationErrors, $e->toString());
            }
        }
    }
    
}
?>