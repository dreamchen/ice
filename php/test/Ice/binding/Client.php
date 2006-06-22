<?
// **********************************************************************
//
// Copyright (c) 2003-2006 ZeroC, Inc. All rights reserved.
//
// This copy of Ice is licensed to you under the terms described in the
// ICE_LICENSE file included in this distribution.
//
// **********************************************************************

error_reporting(E_ALL | E_STRICT);

Ice_loadProfileWithArgs($argv);

function test($b)
{
    if(!$b)
    {
        $bt = debug_backtrace();
        die("\ntest failed in ".$bt[0]["file"]." line ".$bt[0]["line"]."\n");
    }
}

function createTestIntfPrx($adapters)
{
    $endpoints = array();
    $test = null;
    foreach($adapters as $p)
    {
	$test = $p->getTestIntf();
	$edpts = $test->ice_getEndpoints();
	foreach($edpts as $e)
	{
	    $endpoints[] = $e;
	}
    }
    $test = $test->ice_endpoints($endpoints);
    return $test->ice_uncheckedCast("::Test::TestIntf");
}

function deactivate($com, $adapters)
{
    foreach($adapters as $p)
    {
	$com->deactivateObjectAdapter($p);
    }
}

function allTests()
{
    global $ICE;

    $ref = "communicator:default -p 12010 -t 10000";
    $com = $ICE->stringToProxy($ref)->ice_uncheckedCast("::Test::RemoteCommunicator");

    echo "testing binding with single endpoint... ";
    flush();
    {
	$adapter = $com->createObjectAdapter("Adapter", "default");

	$test1 = $adapter->getTestIntf();
	$test2 = $adapter->getTestIntf();
	test($test1->ice_getConnection() == $test2->ice_getConnection());

	$test1->ice_ping();
	$test2->ice_ping();

	$com->deactivateObjectAdapter($adapter);

	$test3 = $test1->ice_uncheckedCast("::Test::TestIntf");
	test($test3->ice_getConnection() == $test1->ice_getConnection());
	test($test3->ice_getConnection() == $test2->ice_getConnection());

	try
	{
	    $test3->ice_ping();
	    test(false);
	}
	catch(Ice_LocalException $ex) // Expecting ConnectionRefusedException
	{
	}
    }
    echo "ok" . "\n";

    echo "testing binding with multiple endpoints... ";
    flush();
    {
	$adapters = array();
	$adapters[] = $com->createObjectAdapter("Adapter11", "default");
	$adapters[] = $com->createObjectAdapter("Adapter12", "default");
	$adapters[] = $com->createObjectAdapter("Adapter13", "default");

	$names = array("Adapter11", "Adapter12", "Adapter13");
	while(count($names) > 0)
	{
	    $adpts = $adapters;

	    $test1 = createTestIntfPrx($adpts);
	    shuffle($adpts);
	    $test2 = createTestIntfPrx($adpts);
	    shuffle($adpts);
	    $test3 = createTestIntfPrx($adpts);

	    test($test1->ice_getConnection() == $test2->ice_getConnection());
	    test($test2->ice_getConnection() == $test3->ice_getConnection());

	    $key = array_search($test1->getAdapterName(), $names);
	    if($key !== false)
	    {
		unset($names[$key]);
	    }
	    $test1->ice_getConnection()->close(false);
	}

	$com->deactivateObjectAdapter($adapters[0]);

	$names = array("Adapter12", "Adapter13");
	while(count($names) > 0)
	{
	    $adpts = $adapters;

	    $test1 = createTestIntfPrx($adpts);
	    shuffle($adpts);
	    $test2 = createTestIntfPrx($adpts);
	    shuffle($adpts);
	    $test3 = createTestIntfPrx($adpts);

	    test($test1->ice_getConnection() == $test2->ice_getConnection());
	    test($test2->ice_getConnection() == $test3->ice_getConnection());

	    $key = array_search($test1->getAdapterName(), $names);
	    if($key !== false)
	    {
		unset($names[$key]);
	    }
	    $test1->ice_getConnection()->close(false);
	}

	$com->deactivateObjectAdapter($adapters[2]);

	$test = createTestIntfPrx($adapters);
	test($test->getAdapterName() == "Adapter12");	

	deactivate($com, $adapters);
    }
    echo "ok" . "\n";

    echo "testing random endpoint selection... ";
    flush();
    {
	$adapters = array();
	$adapters[] = $com->createObjectAdapter("Adapter21", "default");
	$adapters[] = $com->createObjectAdapter("Adapter22", "default");
	$adapters[] = $com->createObjectAdapter("Adapter23", "default");

	$test = createTestIntfPrx($adapters);
	test($test->ice_getEndpointSelection() == Ice_EndpointSelectionType::Random);

	$names = array("Adapter21", "Adapter22", "Adapter23");
	while(count($names) > 0)
	{
	    $key = array_search($test->getAdapterName(), $names);
	    if($key !== false)
	    {
		unset($names[$key]);
	    }
	    $test->ice_getConnection()->close(false);
	}

	$test = $test->ice_endpointSelection(Ice_EndpointSelectionType::Random)->ice_uncheckedCast("::Test::TestIntf");
	test($test->ice_getEndpointSelection() == Ice_EndpointSelectionType::Random);

	$names = array("Adapter21", "Adapter22", "Adapter23");
	while(count($names) > 0)
	{
	    $key = array_search($test->getAdapterName(), $names);
	    if($key !== false)
	    {
		unset($names[$key]);
	    }
	    $test->ice_getConnection()->close(false);
	}

	deactivate($com, $adapters);
    }
    echo "ok" . "\n";

    echo "testing ordered endpoint selection... ";
    flush();
    {
	$adapters = array();
	$adapters[] = $com->createObjectAdapter("Adapter31", "default");
	$adapters[] = $com->createObjectAdapter("Adapter32", "default");
	$adapters[] = $com->createObjectAdapter("Adapter33", "default");

	$test = createTestIntfPrx($adapters);
	$test = $test->ice_endpointSelection(Ice_EndpointSelectionType::Ordered)->ice_uncheckedCast("::Test::TestIntf");
	test($test->ice_getEndpointSelection() == Ice_EndpointSelectionType::Ordered);
	$nRetry = 5;

	//
	// Ensure that endpoints are tried in order by deactiving the adapters
	// one after the other.
	//
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter31"; $i++);
	test($i == $nRetry);
	$com->deactivateObjectAdapter($adapters[0]);
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter32"; $i++);
	test($i == $nRetry);
	$com->deactivateObjectAdapter($adapters[1]);
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter33"; $i++);
	test($i == $nRetry);
	$com->deactivateObjectAdapter($adapters[2]);

	try
	{
	    $test->getAdapterName();
	}
	catch(Ice_LocalException $ex) // Expecting ConnectionRefusedException
	{
	}

	$endpoints = $test->ice_getEndpoints();

	$adapters = array();

	//
	// Now, re-activate the adapters with the same endpoints in the opposite
	// order.
	// 
	$adapters[] = $com->createObjectAdapter("Adapter36", $endpoints[2]->toString());
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter36"; $i++);
	test($i == $nRetry);
	$test->ice_getConnection()->close(true);
	$adapters[] = $com->createObjectAdapter("Adapter35", $endpoints[1]->toString());
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter35"; $i++);
	test($i == $nRetry);
	$test->ice_getConnection()->close(true);
	$adapters[] = $com->createObjectAdapter("Adapter34", $endpoints[0]->toString());
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter34"; $i++);
	test($i == $nRetry);

	deactivate($com, $adapters);
    }
    echo "ok" . "\n";

    echo "testing per request binding with single endpoint... ";
    flush();
    {
	$adapter = $com->createObjectAdapter("Adapter41", "default");

	$test1 = $adapter->getTestIntf()->ice_connectionCached(false)->ice_uncheckedCast("::Test::TestIntf");
	$test2 = $adapter->getTestIntf()->ice_connectionCached(false)->ice_uncheckedCast("::Test::TestIntf");
	test(!$test1->ice_isConnectionCached());
	test(!$test2->ice_isConnectionCached());
	test($test1->ice_getConnection() == $test2->ice_getConnection());

	$test1->ice_ping();

	$com->deactivateObjectAdapter($adapter);

	$test3 = $test1->ice_uncheckedCast("::Test::TestIntf");
	try
	{
	    test($test3->ice_getConnection() == $test1->ice_getConnection());
	    test(false);
	}
	catch(Ice_LocalException $ex) // Expecting ConnectionRefusedException
	{
	}
    }
    echo "ok" . "\n";

    echo "testing per request binding with multiple endpoints... ";
    flush();
    {
	$adapters = array();
	$adapters[] = $com->createObjectAdapter("Adapter51", "default");
	$adapters[] = $com->createObjectAdapter("Adapter52", "default");
	$adapters[] = $com->createObjectAdapter("Adapter53", "default");

	$test = createTestIntfPrx($adapters)->ice_connectionCached(false)->ice_uncheckedCast("::Test::TestIntf");
	test(!$test->ice_isConnectionCached());

	$names = array("Adapter51", "Adapter52", "Adapter53");
	while(count($names) > 0)
	{
	    $key = array_search($test->getAdapterName(), $names);
	    if($key !== false)
	    {
		unset($names[$key]);
	    }
	}

	$com->deactivateObjectAdapter($adapters[0]);

	$names = array("Adapter52", "Adapter53");
	while(count($names) > 0)
	{
	    $key = array_search($test->getAdapterName(), $names);
	    if($key !== false)
	    {
		unset($names[$key]);
	    }
	}

	$com->deactivateObjectAdapter($adapters[2]);

	test($test->getAdapterName() == "Adapter52");

	deactivate($com, $adapters);
    }
    echo "ok" . "\n";

    echo "testing per request binding and ordered endpoint selection... ";
    flush();
    {
	$adapters = array();
	$adapters[] = $com->createObjectAdapter("Adapter61", "default");
	$adapters[] = $com->createObjectAdapter("Adapter62", "default");
	$adapters[] = $com->createObjectAdapter("Adapter63", "default");

	$test = createTestIntfPrx($adapters);
	$test = $test->ice_endpointSelection(Ice_EndpointSelectionType::Ordered)->ice_uncheckedCast("::Test::TestIntf");
	test($test->ice_getEndpointSelection() == Ice_EndpointSelectionType::Ordered);
	$test = $test->ice_connectionCached(false)->ice_uncheckedCast("::Test::TestIntf");
	test(!$test->ice_isConnectionCached());
	$nRetry = 5;

	//
	// Ensure that endpoints are tried in order by deactiving the adapters
	// one after the other.
	//
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter61"; $i++);
	test($i == $nRetry);
	$com->deactivateObjectAdapter($adapters[0]);
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter62"; $i++);
	test($i == $nRetry);
	$com->deactivateObjectAdapter($adapters[1]);
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter63"; $i++);
	test($i == $nRetry);
	$com->deactivateObjectAdapter($adapters[2]);

	try
	{
	    $test->getAdapterName();
	}
	catch(Ice_LocalException $ex) // Expecting ConnectionRefusedException
	{
	}

	$endpoints = $test->ice_getEndpoints();

	$adapters = array();

	//
	// Now, re-activate the adapters with the same endpoints in the opposite
	// order.
	// 
	$adapters[] = $com->createObjectAdapter("Adapter66", $endpoints[2]->toString());
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter66"; $i++);
	test($i == $nRetry);
	$adapters[] = $com->createObjectAdapter("Adapter65", $endpoints[1]->toString());
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter65"; $i++);
	test($i == $nRetry);
	$adapters[] = $com->createObjectAdapter("Adapter64", $endpoints[0]->toString());
	for($i = 0; $i < $nRetry && $test->getAdapterName() == "Adapter64"; $i++);
	test($i == $nRetry);

	deactivate($com, $adapters);
    }
    echo "ok" . "\n";

    echo "testing endpoint mode filtering... ";
    flush();
    {
	$adapters = array();
	$adapters[] = $com->createObjectAdapter("Adapter71", "default");
	$adapters[] = $com->createObjectAdapter("Adapter72", "udp");

	$test = createTestIntfPrx($adapters);
	test($test->getAdapterName() == "Adapter71");

	$testUDP = $test->ice_datagram()->ice_uncheckedCast("::Test::TestIntf");
	test($test->ice_getConnection() != $testUDP->ice_getConnection());
	try
	{
	    $testUDP->getAdapterName();
	}
	catch(Ice_TwowayOnlyException $ex)
	{
	}
    }
    echo "ok" . "\n";

    if(strlen($ICE->getProperty("Ice.Plugin.IceSSL")) > 0)
    {
	echo "testing unsecure vs. secure endpoints... ";
	flush();
	{
	    $adapters = array();
	    $adapters[] = $com->createObjectAdapter("Adapter81", "ssl");
	    $adapters[] = $com->createObjectAdapter("Adapter82", "tcp");

	    $test = createTestIntfPrx($adapters);
	    for($i = 0; $i < 5; $i++)
	    {
		test($test->getAdapterName() == "Adapter82");
		$test->ice_getConnection()->close(false);
	    }

	    $testSecure = $test->ice_secure(true)->ice_uncheckedCast("::Test::TestIntf");
	    //test($testSecure->ice_getSecure());
	    test($test->ice_getConnection() != $testSecure->ice_getConnection());

	    $com->deactivateObjectAdapter($adapters[1]);

	    for($i = 0; $i < 5; $i++)
	    {
		test($test->getAdapterName() == "Adapter81");
		$test->ice_getConnection()->close(false);
	    }

	    $endpts = $test->ice_getEndpoints();
	    $com->createObjectAdapter("Adapter83", $endpts[1]->toString()); // Reactive tcp OA.

	    for($i = 0; $i < 5; $i++)
	    {
		test($test->getAdapterName() == "Adapter83");
		$test->ice_getConnection()->close(false);
	    }

	    $com->deactivateObjectAdapter($adapters[0]);
	    try
	    {
		$testSecure->ice_ping();
		test(false);
	    }
	    catch(Ice_LocalException $ex) // Expecting ConnectionRefusedException
	    {
	    }

	    deactivate($com, $adapters);
	}
	echo "ok" . "\n";
    }

    $com->shutdown();
}

allTests();
exit();
?>
