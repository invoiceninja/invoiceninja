<?php
//
// PHP framework for testing, based on the design of "JUnit".
//
// Written by Fred Yankowski <fred@ontosys.com>
//            OntoSys, Inc  <http://www.OntoSys.com>
//
// $Id: phpunit.php,v 1.11 2002/10/31 18:44:12 fredy Exp $

// Copyright (c) 2000 Fred Yankowski

// Permission is hereby granted, free of charge, to any person
// obtaining a copy of this software and associated documentation
// files (the "Software"), to deal in the Software without
// restriction, including without limitation the rights to use, copy,
// modify, merge, publish, distribute, sublicense, and/or sell copies
// of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
// BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
// ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
// CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
//
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE |
		E_CORE_ERROR | E_CORE_WARNING);

/*
interface Test {
  function run(&$aTestResult);
  function countTestCases();
}
*/

function trace($msg) {
  return;
  print($msg);
  flush();
}

if (phpversion() >= '4') {
    function PHPUnit_error_handler($errno, $errstr, $errfile, $errline) {
	global $PHPUnit_testRunning;
	$PHPUnit_testRunning[0]->fail("<B>PHP ERROR:</B> ".$errstr." <B>in</B> ".$errfile." <B>at line</B> ".$errline);
    }
}

class Exception {
    /* Emulate a Java exception, sort of... */
  var $message;
  var $type;
  function Exception($message, $type = 'FAILURE') {
    $this->message = $message;
    $this->type = $type;
  }
  function getMessage() {
    return $this->message;
  }
  function getType() {
    return $this->type;
  }
}

class Assert {
  function assert($boolean, $message=0) {
    if (! $boolean)
      $this->fail($message);
  }

  function assertEquals($expected, $actual, $message=0) {
      if (gettype($expected) != gettype($actual)) {
	  $this->failNotEquals($expected, $actual, "expected", $message);
	  return;
      }
      if (phpversion() < '4') {
	  if (is_object($expected) or is_object($actual)
	      or is_array($expected) or is_array($actual)) {
	      $this->error("INVALID TEST: cannot compare arrays or objects in PHP3");
	      return;
	  }
      }
      if (phpversion() >= '4' && is_object($expected)) {
	  if (get_class($expected) != get_class($actual)) {
	      $this->failNotEquals($expected, $actual, "expected", $message);
	      return;
	  }
	  if (method_exists($expected, "equals")) {
	      if (! $expected->equals($actual)) {
		  $this->failNotEquals($expected, $actual, "expected", $message);
	      }
	      return;		// no further tests after equals()

	  }
      }
      if (phpversion() >= '4.0.4') {
	  if (is_null($expected) != is_null($actual)) {
	      $this->failNotEquals($expected, $actual, "expected", $message);
	      return;
	  }
      }
      if ($expected != $actual) {
	  $this->failNotEquals($expected, $actual, "expected", $message);
      }
  }

  function assertRegexp($regexp, $actual, $message=false) {
    if (! preg_match($regexp, $actual)) {
      $this->failNotEquals($regexp, $actual, "pattern", $message);
    }
  }

  function assertEqualsMultilineStrings($string0, $string1,
  $message="") {
    $lines0 = split("\n",$string0);
    $lines1 = split("\n",$string1);
    if (sizeof($lines0) != sizeof($lines1)) {
      $this->failNotEquals(sizeof($lines0)." line(s)",
                           sizeof($lines1)." line(s)", "expected", $message);
    }
    for($i=0; $i< sizeof($lines0); $i++) {
      $this->assertEquals(trim($lines0[$i]),
                          trim($lines1[$i]),
                          "line ".($i+1)." of multiline strings differ. ".$message); 
    }
  }

  function _formatValue($value, $class="") {
      $translateValue = $value;
      if (phpversion() >= '4.0.0') {
	  if (is_object($value)) {
	      if (method_exists($value, "toString") ) {
		  $translateValue = $value->toString();
	      }
	      else {
		  $translateValue = serialize($value);
	      }
	  }
	  else if (is_array($value)) {
	      $translateValue = serialize($value);
	  }
      }
      $htmlValue = "<code class=\"$class\">"
	   . htmlspecialchars($translateValue) . "</code>";
      if (phpversion() >= '4.0.0') {
	  if (is_bool($value)) {
	      $htmlValue = $value ? "<i>true</i>" : "<i>false</i>";
	  }
	  elseif (phpversion() >= '4.0.4' && is_null($value)) {
	      $htmlValue = "<i>null</i>";
	  }
	  $htmlValue .= "&nbsp;&nbsp;&nbsp;<span class=\"typeinfo\">";
	  $htmlValue .= "type:" . gettype($value);
	  $htmlValue .= is_object($value) ? ", class:" . get_class($value) : "";
	  $htmlValue .= "</span>";
      }
      return $htmlValue;
  }

  function failNotEquals($expected, $actual, $expected_label, $message=0) {
    // Private function for reporting failure to match.
    $str = $message ? ($message . ' ') : '';
    //$str .= "($expected_label/actual)<br>";
    $str .= "<br>";
    $str .= sprintf("%s<br>%s",
		    $this->_formatValue($expected, "expected"),
		    $this->_formatValue($actual, "actual"));
    $this->fail($str);
  }
}

class TestCase extends Assert /* implements Test */ {
  /* Defines context for running tests.  Specific context -- such as
     instance variables, global variables, global state -- is defined
     by creating a subclass that specializes the setUp() and
     tearDown() methods.  A specific test is defined by a subclass
     that specializes the runTest() method. */
  var $fName;
  var $fClassName;
  var $fResult;
  var $fExceptions = array();

  function TestCase($name) {
    $this->fName = $name;
  }

  function run($testResult=0) {
    /* Run this single test, by calling the run() method of the
       TestResult object which will in turn call the runBare() method
       of this object.  That complication allows the TestResult object
       to do various kinds of progress reporting as it invokes each
       test.  Create/obtain a TestResult object if none was passed in.
       Note that if a TestResult object was passed in, it must be by
       reference. */
    if (! $testResult)
      $testResult = $this->_createResult();
    $this->fResult = $testResult;
    $testResult->run(&$this);
    $this->fResult = 0;
    return $testResult;
  }
  
  function classname() {
	  if (isset($this->fClassName)) {
		return $this->fClassName;
	  } else {
		return get_class($this);
	  }
  }

  function countTestCases() {
    return 1;
  }

  function runTest() {
    if (phpversion() >= '4') {
	global $PHPUnit_testRunning;
	eval('$PHPUnit_testRunning[0] = & $this;');
	// Saved ref to current TestCase, so that the error handler
	// can access it.  This code won't even parse in PHP3, so we
	// hide it in an eval.

	$old_handler = set_error_handler("PHPUnit_error_handler");
	// errors will now be handled by our error handler
      }

      $name = $this->name();
      if (phpversion() >= '4' && ! method_exists($this, $name)) {
          $this->error("Method '$name' does not exist");
      }
      else
          $this->$name();

//      if (phpversion() >= '4') {
//	  set_error_handler($old_handler); // revert to prior error handler
//	  $PHPUnit_testRunning = null;
//      }
  }

  function setUp() /* expect override */ {
    //print("TestCase::setUp()<br>\n");
  }

  function tearDown() /* possible override */ {
    //print("TestCase::tearDown()<br>\n");
  }

  ////////////////////////////////////////////////////////////////


  function _createResult() /* protected */ {
    /* override this to use specialized subclass of TestResult */
    return new TestResult;
  }

  function fail($message=0) {
    //printf("TestCase::fail(%s)<br>\n", ($message) ? $message : '');
    /* JUnit throws AssertionFailedError here.  We just record the
       failure and carry on */
    $this->fExceptions[] = new Exception(&$message, 'FAILURE');
  }

  function error($message) {
    /* report error that requires correction in the test script
       itself, or (heaven forbid) in this testing infrastructure */
    $this->fExceptions[] = new Exception(&$message, 'ERROR');
    $this->fResult->stop();	// [does not work]
  }

  function failed() {
      reset($this->fExceptions);
      while (list($key, $exception) = each($this->fExceptions)) {
	  if ($exception->type == 'FAILURE')
	      return true;
      }
      return false;
  }
  function errored() {
      reset($this->fExceptions);
      while (list($key, $exception) = each($this->fExceptions)) {
	  if ($exception->type == 'ERROR')
	      return true;
      }
      return false;
  }

  function getExceptions() {
    return $this->fExceptions;
  }

  function name() {
    return $this->fName;
  }

  function runBare() {
    $this->setup();
    $this->runTest();
    $this->tearDown();
  }
}


class TestSuite /* implements Test */ {
  /* Compose a set of Tests (instances of TestCase or TestSuite), and
     run them all. */
  var $fTests = array();
  var $fClassname;

  function TestSuite($classname=false) {
    // Find all methods of the given class whose name starts with
    // "test" and add them to the test suite.

    // PHP3: We are just _barely_ able to do this with PHP's limited
    // introspection...  Note that PHP seems to store method names in
    // lower case, and we have to avoid the constructor function for
    // the TestCase class superclass.  Names of subclasses of TestCase
    // must not start with "Test" since such a class will have a
    // constructor method name also starting with "test" and we can't
    // distinquish such a construtor from the real test method names.
    // So don't name any TestCase subclasses as "Test..."!

    // PHP4:  Never mind all that.  We can now ignore constructor
    // methods, so a test class may be named "Test...".

    if (empty($classname))
      return;
    $this->fClassname = $classname;

    if (floor(phpversion()) >= 4) {
      // PHP4 introspection, submitted by Dylan Kuhn

      $names = get_class_methods($classname);
      while (list($key, $method) = each($names)) {
        if (preg_match('/^test/', $method)) {
          $test = new $classname($method);
          if (strcasecmp($method, $classname) == 0 || is_subclass_of($test, $method)) {
            // Ignore the given method name since it is a constructor:
            // it's the name of our test class or it is the name of a
            // superclass of our test class.  (This code smells funny.
            // Anyone got a better way?)

            //print "skipping $method<br>";
          }
          else {
            $this->addTest($test);
          }
        }
      }
    }
    else {  // PHP3
      $dummy = new $classname("dummy");
      $names = (array) $dummy;
      while (list($key, $value) = each($names)) {
        $type = gettype($value);
        if ($type == "user function" && preg_match('/^test/', $key)
        && $key != "testcase") {  
          $this->addTest(new $classname($key));
        }
      }
    }
  }

  function addTest($test) {
    /* Add TestCase or TestSuite to this TestSuite */
    $this->fTests[] = $test;
  }

  function run(&$testResult) {
    /* Run all TestCases and TestSuites comprising this TestSuite,
       accumulating results in the given TestResult object. */
    reset($this->fTests);
    while (list($na, $test) = each($this->fTests)) {
      if ($testResult->shouldStop())
	break;
      $test->run(&$testResult);
    }
  }

  function countTestCases() {
    /* Number of TestCases comprising this TestSuite (including those
       in any constituent TestSuites) */
    $count = 0;
    reset($fTests);
    while (list($na, $test_case) = each($this->fTests)) {
      $count += $test_case->countTestCases();
    }
    return $count;
  }
}


class TestFailure {
  /* Record failure of a single TestCase, associating it with the
     exception that occurred */
  var $fFailedTestName;
  var $fException;

  function TestFailure(&$test, &$exception) {
    $this->fFailedTestName = $test->name();
    $this->fException = $exception;
  }

  function getExceptions() {
      // deprecated
      return array($this->fException);
  }
  function getException() {
      return $this->fException;
  }

  function getTestName() {
    return $this->fFailedTestName;
  }
}


class TestResult {
  /* Collect the results of running a set of TestCases. */
  var $fFailures = array();
  var $fErrors = array();
  var $fRunTests = 0;
  var $fStop = false;

  function TestResult() { }

  function _endTest($test) /* protected */ {
      /* specialize this for end-of-test action, such as progress
	 reports  */
  }

  function addError($test, $exception) {
      $this->fErrors[] = new TestFailure(&$test, &$exception);
  }

  function addFailure($test, $exception) {
      $this->fFailures[] = new TestFailure(&$test, &$exception);
  }

  function getFailures() {
    return $this->fFailures;
  }

  function run($test) {
    /* Run a single TestCase in the context of this TestResult */
    $this->_startTest($test);
    $this->fRunTests++;

    $test->runBare();

    /* this is where JUnit would catch AssertionFailedError */
    $exceptions = $test->getExceptions();
    reset($exceptions);
    while (list($key, $exception) = each($exceptions)) {
	if ($exception->type == 'ERROR')
	    $this->addError($test, $exception);
	else if ($exception->type == 'FAILURE')
	    $this->addFailure($test, $exception);
    }
    //    if ($exceptions)
    //      $this->fFailures[] = new TestFailure(&$test, &$exceptions);
    $this->_endTest($test);
  }

  function countTests() {
    return $this->fRunTests;
  }

  function shouldStop() {
    return $this->fStop;
  }

  function _startTest($test) /* protected */ {
      /* specialize this for start-of-test actions */
  }

  function stop() {
    /* set indication that the test sequence should halt */
    $fStop = true;
  }

  function errorCount() {
      return count($this->fErrors);
  }
  function failureCount() {
      return count($this->fFailures);
  }
  function countFailures() {
      // deprecated
      return $this->failureCount();
  }
}


class TextTestResult extends TestResult {
  /* Specialize TestResult to produce text/html report */
  function TextTestResult() {
    $this->TestResult();  // call superclass constructor
  }
  
  function report() {
    /* report result of test run */
    $nRun = $this->countTests();
    $nFailures = $this->failureCount();
    $nErrors = $this->errorCount();
    printf("<p>%s test%s run<br>", $nRun, ($nRun == 1) ? '' : 's');
    printf("%s failure%s<br>\n", $nFailures, ($nFailures == 1) ? '' : 's');
    printf("%s error%s.<br>\n", $nErrors, ($nErrors == 1) ? '' : 's');

    if ($nFailures > 0) {
	print("<h2>Failures</h2>");
	print("<ol>\n");
	$failures = $this->getFailures();
	while (list($i, $failure) = each($failures)) {
	    $failedTestName = $failure->getTestName();
	    printf("<li>%s\n", $failedTestName);

	    $exceptions = $failure->getExceptions();
	    print("<ul>");
	    while (list($na, $exception) = each($exceptions))
		printf("<li>%s\n", $exception->getMessage());
	    print("</ul>");
	}
	print("</ol>\n");
    }

    if ($nErrors > 0) {
	print("<h2>Errors</h2>");
	print("<ol>\n");
	reset($this->fErrors);
	while (list($i, $error) = each($this->fErrors)) {
	    $erroredTestName = $error->getTestName();
	    printf("<li>%s\n", $failedTestName);

	    $exception = $error->getException();
	    print("<ul>");
	    printf("<li>%s\n", $exception->getMessage());
	    print("</ul>");
	}
	print("</ol>\n");

    }
  }

  function _startTest($test) {
      if (phpversion() > '4') {
	  printf("%s - %s ", get_class($test), $test->name());
      } else {
	  printf("%s ", $test->name());
      }
    flush();
  }

  function _endTest($test) {
    $outcome = $test->failed()
       ? "<font color=\"red\">FAIL</font>"
       : "<font color=\"green\">ok</font>";
    printf("$outcome<br>\n");
    flush();
  }
}

// PrettyTestResult created by BJG 17/11/01
// beacuse the standard test result provided looks
// rubbish.
class PrettyTestResult extends TestResult {
  /* Specialize TestResult to produce text/html report */
  function PrettyTestResult() {
    $this->TestResult();  // call superclass constructor
	echo "<h2>Tests</h2>";
	
	echo "<TABLE CELLSPACING=\"1\" CELLPADDING=\"1\" BORDER=\"0\" WIDTH=\"90%\" ALIGN=\"CENTER\" class=\"details\">";
	echo "<TR><TH>Class</TH><TH>Function</TH><TH>Success?</TH></TR>";
  }
  
  function report() {
	echo "</TABLE>";
    /* report result of test run */
    $nRun = $this->countTests();
    $nFailures = $this->countFailures();
	echo "<h2>Summary</h2>";

    printf("<p>%s test%s run<br>", $nRun, ($nRun == 1) ? '' : 's');
    printf("%s failure%s.<br>\n", $nFailures, ($nFailures == 1) ? '' : 's');
    if ($nFailures == 0)
      return;

	echo "<h2>Failure Details</h2>";
    print("<ol>\n");
    $failures = $this->getFailures();
    while (list($i, $failure) = each($failures)) {
      $failedTestName = $failure->getTestName();
      printf("<li>%s\n", $failedTestName);

      $exceptions = $failure->getExceptions();
      print("<ul>");
      while (list($na, $exception) = each($exceptions))
	printf("<li>%s\n", $exception->getMessage());
      print("</ul>");
    }
    print("</ol>\n");
  }

  function _startTest($test) {
    printf("<TR><TD>%s </TD><TD>%s </TD>", $test->classname(),$test->name());
    flush();
  }

  function _endTest($test) {
    $outcome = $test->failed()
       ? " class=\"Failure\">FAIL"
       : " class=\"Pass\">OK";
    printf("<TD$outcome</TD></TR>");
    flush();
  }
}

class TestRunner {
  /* Run a suite of tests and report results. */
  function run($suite) {
      $result = new TextTestResult;
      $suite->run($result);
      $result->report();
  }
}

?>