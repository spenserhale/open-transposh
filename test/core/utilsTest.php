<?php
ini_set("include_path", "../core".PATH_SEPARATOR."../../core".PATH_SEPARATOR.ini_get("include_path"));
define ("DEBUG" , 5);
require_once 'PHPUnit/Framework.php';
require_once 'utils.php';

/**
 * Test class for utils.
 * Generated by PHPUnit on 2009-06-15 at 13:21:30.
 */
class utilsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var    utils
     * @access protected
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        define ("DEBUG" , 5);
        define ("PRINTOUT" , true);
        define ("EOLPRINT" , true);
        $GLOBALS[home_url] = "http://transposh.org";
//        $GLOBALS[home_url_quoted] = "http\:\/\/transposh\.org";
        $GLOBALS[enable_permalinks_rewrite] = true;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
    }

    public function testRewriteURL()
    {
        $edit = false;
        $params_only = false;
        $this->assertEquals("/he/",rewrite_url_lang_param("","he", $edit, $params_only));
        $this->assertEquals("/he/",rewrite_url_lang_param("/","he", $edit, $params_only));
        $this->assertEquals("/he/test",rewrite_url_lang_param("/test","he", $edit,$params_only));
        $this->assertEquals("/he/test/",rewrite_url_lang_param("/test/","he", $edit,$params_only));
        $this->assertEquals("/he/test/",rewrite_url_lang_param("/test/?lang=en","he", $edit,$params_only));
        $this->assertEquals("/he/test/",rewrite_url_lang_param("/en/test/?lang=en","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/",rewrite_url_lang_param("http://www.islands.co.il/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he",rewrite_url_lang_param("http://www.islands.co.il/he","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr",rewrite_url_lang_param("http://www.islands.co.il/fr","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he/",rewrite_url_lang_param("http://www.islands.co.il/he/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr/",rewrite_url_lang_param("http://www.islands.co.il/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/he","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/fr","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/he/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/zh-tw/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/37/",rewrite_url_lang_param("http://transposh.org/37/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh-tw&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&amp;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&#038;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?cat=y",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&#038;edit=1&cat=y","he", $edit,$params_only));
    }

    public function testRewriteURLedit()
    {
        $edit = true;
        $params_only = false;
        $this->assertEquals("/he/?edit=1",rewrite_url_lang_param("","he", $edit, $params_only));
        $this->assertEquals("/he/?edit=1",rewrite_url_lang_param("/","he", $edit, $params_only));
        $this->assertEquals("/he/test?edit=1",rewrite_url_lang_param("/test","he", $edit,$params_only));
        $this->assertEquals("/he/test/?edit=1",rewrite_url_lang_param("/test/","he", $edit,$params_only));
        $this->assertEquals("/he/test/?edit=1",rewrite_url_lang_param("/test/?lang=en","he", $edit,$params_only));
        $this->assertEquals("/he/test/?edit=1",rewrite_url_lang_param("/en/test/?lang=en","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/",rewrite_url_lang_param("http://www.islands.co.il/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he",rewrite_url_lang_param("http://www.islands.co.il/he","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr",rewrite_url_lang_param("http://www.islands.co.il/fr","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he/",rewrite_url_lang_param("http://www.islands.co.il/he/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr/",rewrite_url_lang_param("http://www.islands.co.il/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/he","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/fr","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/he/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/zh-tw/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/37/?edit=1",rewrite_url_lang_param("http://transposh.org/37/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh-tw&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&amp;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?edit=1",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&#038;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/he/?cat=y&edit=1",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&#038;edit=1&cat=y","he", $edit,$params_only));
    }

    public function testRewriteURLparams()
    {
        $edit = false;
        $params_only = true;
        $this->assertEquals("?lang=he",rewrite_url_lang_param("","he", $edit, $params_only));
        $this->assertEquals("/?lang=he",rewrite_url_lang_param("/","he", $edit, $params_only));
        $this->assertEquals("/test?lang=he",rewrite_url_lang_param("/test","he", $edit,$params_only));
        $this->assertEquals("/test/?lang=he",rewrite_url_lang_param("/test/","he", $edit,$params_only));
        $this->assertEquals("/test/?lang=he",rewrite_url_lang_param("/test/?lang=en","he", $edit,$params_only));
        $this->assertEquals("/test/?lang=he",rewrite_url_lang_param("/en/test/?lang=en","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/",rewrite_url_lang_param("http://www.islands.co.il/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he",rewrite_url_lang_param("http://www.islands.co.il/he","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr",rewrite_url_lang_param("http://www.islands.co.il/fr","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he/",rewrite_url_lang_param("http://www.islands.co.il/he/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr/",rewrite_url_lang_param("http://www.islands.co.il/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/he","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/fr","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/he/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/zh-tw/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/37/?lang=he",rewrite_url_lang_param("http://transposh.org/37/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh-tw&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&amp;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?lang=he",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&#038;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/?cat=y&lang=he",rewrite_url_lang_param("http://transposh.org/fr/?lang=zh&#038;edit=1&cat=y","he", $edit,$params_only));
    }

    public function testRewriteURLwithsubdir()
    {
        $GLOBALS[home_url] = "http://transposh.org/test/";
        $edit = false;
        $params_only = false;
        $this->assertEquals("/he/",rewrite_url_lang_param("","he", $edit, $params_only));
        $this->assertEquals("/he/",rewrite_url_lang_param("/","he", $edit, $params_only));
        $this->assertEquals("/test/he/",rewrite_url_lang_param("/test","he", $edit,$params_only));
        $this->assertEquals("/test/he/",rewrite_url_lang_param("/test/","he", $edit,$params_only));
        $this->assertEquals("/test/he/",rewrite_url_lang_param("/test/?lang=en","he", $edit,$params_only));
        $this->assertEquals("/test/he/",rewrite_url_lang_param("/test/en/?lang=en","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/",rewrite_url_lang_param("http://www.islands.co.il/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he",rewrite_url_lang_param("http://www.islands.co.il/he","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr",rewrite_url_lang_param("http://www.islands.co.il/fr","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/he/",rewrite_url_lang_param("http://www.islands.co.il/he/","he", $edit,$params_only));
        $this->assertEquals("http://www.islands.co.il/fr/",rewrite_url_lang_param("http://www.islands.co.il/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/he","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/fr","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/he/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/fr/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/zh-tw/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/37/",rewrite_url_lang_param("http://transposh.org/test/37/","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/fr/?lang=zh","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/fr/?lang=zh&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/fr/?lang=zh-tw&edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/fr/?lang=zh&amp;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/",rewrite_url_lang_param("http://transposh.org/test/fr/?lang=zh&#038;edit=1","he", $edit,$params_only));
        $this->assertEquals("http://transposh.org/test/he/?cat=y",rewrite_url_lang_param("http://transposh.org/test/fr/?lang=zh&#038;edit=1&cat=y","he", $edit,$params_only));
    }

    public function testCleanupURL()
    {
        $GLOBALS[home_url] = "http://www.algarve-abc.de/ferienhaus-westalgarve/";
        $this->assertEquals("http://www.algarve-abc.de/ferienhaus-westalgarve/test",cleanup_url("http://www.algarve-abc.de/ferienhaus-westalgarve/en/test"));
        $this->assertEquals("http://www.algarve-abc.de/ferienhaus-westalgarve",cleanup_url("http://www.algarve-abc.de/ferienhaus-westalgarve/en"));
        $this->assertEquals("http://www.algarve-abc.de/ferienhaus-westalgarve/",cleanup_url("http://www.algarve-abc.de/ferienhaus-westalgarve/en/"));
        $this->assertEquals("/ferienhaus-westalgarve/",cleanup_url("http://www.algarve-abc.de/ferienhaus-westalgarve/en/", true));
    }
}
?>
