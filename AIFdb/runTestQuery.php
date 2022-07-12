// Note: should be secured against SQL injection
<?php

$host = "localhost";

// Manually get JSON file (for use in dev)
$json_file = dirname(__FILE__).'/test-files-temp/twitter-3915-in-xAIF.json';
$jsondata = file_get_contents($json_file);
$json = json_decode($jsondata);

$nodes = $json->AIF->nodes;
$edges = $json->AIF->edges;

$nodes = array();
foreach ($json->AIF->nodes as $node) {
    $obj = (object)array(
        'nodeID' => $node->nodeID,
        'text' => $node->text,
        'type' => $node->type,
        'edges' => array(),
        'visited' => false,
        'diversion_point' => false);

    $nodes[$obj->nodeID] = $obj;
}

foreach ($json->AIF->edges as $edge) {
    $nodes[$edge->fromID]->edges[] = $edge;
    $nodes[$edge->toID]->edges[] = $edge;
}

$edges = array();
foreach ($json->AIF->edges as $edge) {
    $obj = (object)array(
        'edgeID' => $edge->edgeID,
        'fromID' => $edge->fromID,
        'toID' => $edge->toID,
        'visited' => false);

    $edges[$obj->edgeID] = $obj;
}

$lastEdge = null;
$paths = array();
$pathDirections = array();
$diversionPoints[58785] = $nodes[58785];

$test = array(1,2,3);
foreach ($test as $tt) {
    $keys = array_keys($test);
    unset($test[2]);
}

for ($i = 0; $i < count($diversionPoints); $i++) {

//    $diversionPointsKeys = array_keys($diversionPoints);
//    $currentNode = $diversionPoints[$diversionPointsKeys[$i]];

    $tmp = array_values($diversionPoints);
    $currentNode = array_values($diversionPoints)[$i];
    $currentPath = array();
    $currentPathDirection = array();

    while ($currentNode != null) {

        if ($lastEdge != null)
            $edges[$lastEdge->edgeID]->visited = true;

        $currentPath[] = $currentNode;

        $nextNode = null;
        $unvisitedEdgesCount = 0;
        foreach ($currentNode->edges as $edge) {
            if (!($edges[$edge->edgeID]->visited)) {
                $lastEdge = $edges[$edge->edgeID];
                $unvisitedEdgesCount++;

                $nextNode = $nodes[$edge->fromID];
                if ($nextNode == $currentNode)
                    $nextNode = $nodes[$edge->toID];
            }
        }

        if ($unvisitedEdgesCount > 1) {
            $currentNode->diversion_point = true;
            $diversionPoints[$currentNode->nodeID] = $currentNode;
        }
//        else if ($unvisitedEdgesCount == 0) {
//            $currentNode->diversion_point = false;
//            // TODO: what if the element isn't in the array
//            unset($diversionPoints[$currentNode->nodeID]);
//        }

        $currentNode = $nextNode;

        if($lastEdge->toID == $currentNode->nodeID)
            $currentPathDirection[] = ">";
        else
            $currentPathDirection[] = "<";
    }

    if(count($currentPath) > 1) {
        $paths[] = $currentPath;
        $pathDirections[] = $currentPathDirection;
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli("arg-tech-aifdb_mysql:3306", "root", "@rgtech", "aifdb");

echo $mysqli->host_info . "\n";

//$currentSubdiagram = array("L", ">","TA", ">", "L", ">", "YA", ">", "I", ">", "CA", ">", "YA", "<", "TA");
//$currentSubdiagram = array("L", "TA");
$subdiagramText = array("NULL", "NULL", "NULL", "NULL", "NULL", "NULL", "NULL", "NULL", "NULL");

$t = microtime(TRUE);

$mysqli->query("CREATE TABLE diversion_table_example LIKE nodes");
$mysqli->query(
    "SELECT * FROM (
  SELECT CONCAT('DROP TABLE ', GROUP_CONCAT(table_name) , ';')
  FROM INFORMATION_SCHEMA.TABLES
  WHERE table_name LIKE 'diversion_table_%'
) a INTO @mystmt;");
$mysqli->query("PREPARE mystatement FROM @mystmt;");
$mysqli->query("EXECUTE mystatement");

$queryPart1 = "
    CREATE TEMPORARY TABLE temp_table_n1 (
	nodeID int(10),
    text longtext,
    type varchar(2),
    nodeSetID int(10),
    history longtext);
        ";
$mysqli->query($queryPart1);

$queryPart2 = "
    CREATE TEMPORARY TABLE temp_table_n2 as (
	SELECT * FROM temp_table_n1
	LIMIT 0);
        ";
$mysqli->query($queryPart2);

$queryPart5 = "
    CREATE TEMPORARY TABLE final_results LIKE temp_table_n1";
$mysqli->query($queryPart5);

foreach($paths as $currentPathIndex => $currentSubdiagram) {

    $currentPathDirection = $pathDirections[$currentPathIndex];

    $queryPart3 = "
    INSERT INTO temp_table_n1 
    SELECT n2.nodeID, n2.text, n2.type, nm1.nodeSetID, CONCAT(n1.nodeID, '-', n1.type, ' < ', n2.nodeID, '-', n2.type)
    FROM
	edges e1
    JOIN nodes n1 ON (
        ((\"{$currentPathDirection[0]}\" = \">\" AND n1.nodeID = e1.fromID) OR (\"{$currentPathDirection[0]}\" = \"<\" AND n1.nodeID = e1.toID)) AND 
        (n1.type = \"{$currentSubdiagram[0]->type}\") AND
        (n1.text = \"$subdiagramText[0]\" OR \"$subdiagramText[0]\" = \"NULL\"))
    JOIN nodes n2 ON (
        ((\"{$currentPathDirection[0]}\" = \">\" AND n2.nodeID = e1.toID) OR (\"{$currentPathDirection[0]}\" = \"<\" AND n2.nodeID = e1.fromID)) AND 
        (n2.type = \"{$currentSubdiagram[1]->type}\") AND
        (n2.text = \"$subdiagramText[1]\" OR \"$subdiagramText[1]\" = \"NULL\"))
    JOIN nodeSetMappings nm1 ON (nm1.nodeID = n1.nodeID)
    JOIN nodeSetMappings nm2 ON (nm2.nodeID = n2.nodeID)
    WHERE (nm1.nodeSetID = nm2.nodeSetID)
    GROUP BY n2.nodeID, n2.text, n2.type, nm1.nodeSetID, n1.nodeID;
    ";
    $mysqli->query($queryPart3);

// If the diversion node is the first node, there's no diversion table
    if($currentSubdiagram[0]->diversion_point) {
        $mysqli->query("DROP TABLE IF EXISTS diversion_table_{$currentSubdiagram[0]->nodeID};");
        $queryPart9 = "
            CREATE TABLE diversion_table_{$currentSubdiagram[0]->nodeID} as (
	        SELECT n.nodeID, n.text, n.type, nm.nodeSetID, CONCAT('','')
	        FROM nodes n 
	        JOIN nodeSetMappings nm ON (nm.nodeID = n.nodeID)
	        WHERE n.type = \"{$currentSubdiagram[0]->type}\");
                ";
        $mysqli->query($queryPart9);
    }

    $numOfNodes = count($currentSubdiagram);
    for ($i = 1; $i < $numOfNodes - 1; $i++) {
//for ($i = 1; $i < 8; $i++) {
        $j = $i + 1;

        $queryPart4 = "
        INSERT INTO temp_table_n2
        SELECT n2.nodeID, n2.text, n2.type, nm.nodeSetID, CONCAT(n1.history, ' < ', n2.nodeID, '-', n2.type)
        FROM
	    edges e1
        JOIN temp_table_n1 n1 ON (
            ((\"{$currentPathDirection[$i]}\" = \">\" AND n1.nodeID = e1.fromID) OR (\"{$currentPathDirection[$i]}\" = \"<\" AND n1.nodeID = e1.toID)) AND 
            (n1.type = \"{$currentSubdiagram[$i]->type}\") AND
            (n1.text = \"$subdiagramText[$i]\" OR \"$subdiagramText[$i]\" = \"NULL\"))
        JOIN nodes n2 ON (
            ((\"{$currentPathDirection[$i]}\" = \">\" AND n2.nodeID = e1.toID) OR (\"{$currentPathDirection[$i]}\" = \"<\" AND n2.nodeID = e1.fromID)) AND 
            (n2.type = \"{$currentSubdiagram[$j]->type}\") AND
            (n2.text = \"$subdiagramText[$j]\" OR \"$subdiagramText[$j]\" = \"NULL\"))
        -- DEBUG: JOIN nodeSetMappings nm ON (n2.nodeID = nm.nodeID)
        -- DEBUG: WHERE nm.nodeSetID = 17874
        JOIN nodeSetMappings nm ON (nm.nodeID = n2.nodeID)
        WHERE (nm.nodeSetID = n1.nodeSetID)
        GROUP BY n2.nodeID, n2.text, n2.type, nm.nodeSetID, n1.history;
        ";
        $mysqli->query($queryPart4);

        if($currentSubdiagram[$i]->diversion_point) {
            $queryPart9 = "
            CREATE TABLE diversion_table_{$currentSubdiagram[$i]->nodeID} as (
	        SELECT * FROM temp_table_n2);
                ";
            $mysqli->query($queryPart9);
        }

        $mysqli->query("TRUNCATE TABLE temp_table_n1;");
        $mysqli->query("INSERT INTO temp_table_n1 SELECT * FROM temp_table_n2;");
        $mysqli->query("TRUNCATE TABLE temp_table_n2;");
    }

    if($currentPathIndex == 0) {
        $queryPart7 = "
            INSERT INTO final_results
            SELECT * FROM temp_table_n1
            ";
        $mysqli->query($queryPart7);
    }
    else {
//        $j = $i - 1;
//        $mysqli->query(
//                "
//                INSERT INTO temp_table_n2
//                SELECT n1.*
//                FROM temp_table_n1 n1
//                JOIN diversion_table_{$currentSubdiagram[$i]->nodeID} d
//                ON n1.nodeSetID = d.nodeSetID"
//        );

        $mysqli->query(
            "
            DELETE FROM temp_table_n1
            WHERE NOT EXISTS (
                SELECT *
                FROM diversion_table_{$currentSubdiagram[$i]->nodeID}
                WHERE nodeSetID=temp_table_n1.nodeSetID
            )
            ");

        $queryPart6 = "
            DELETE FROM final_results
            WHERE NOT EXISTS (
                SELECT *
                FROM temp_table_n1
                WHERE nodeSetID=final_results.nodeSetID
            )
            ";
        $mysqli->query($queryPart6);
    }

    $queryPart8 = "
        SELECT *
        FROM temp_table_n1;
    ";
    $a = $mysqli->query($queryPart8);
    $b = $a->num_rows;
    $c = $b;

    $mysqli->query("TRUNCATE TABLE temp_table_n1;");
    $mysqli->query("TRUNCATE TABLE temp_table_n2;");
}

$queryGetResults = "
    SELECT n1.nodeID, n1.text, n1.type, n1.nodeSetID, n1.history
    FROM final_results n1;
    ";

// Perform query
if ($result = $mysqli -> query($queryGetResults)) {

    echo "<br> Time for query: " . (microtime(TRUE)-$t);

    echo "<br> Returned rows are: " . $result -> num_rows . "<br>";

    echo "<table><tr><th>Node</th><th>Nodeset</th><th>Path</th><th>Text</th></tr>";

    if (mysqli_num_rows($result) > 0) {
        while($rowData = mysqli_fetch_array($result)){

            echo "<tr>";

            # Print the node ID and link to node view
            echo "<td><a href='" . "/nodeview/" . $rowData["nodeID"]. "'>" . $rowData["nodeID"] . "</a></td>";
            # Print the nodeset ID and link to argument map view
            echo "<td><a href='" . "/argview/" . $rowData["nodeSetID"]. "?plus=on'>" . $rowData["nodeSetID"] . "</a></td>";
            # Print the path to the node
            echo "<td>" . $rowData["history"] . "</a></td>";
            # Print the text column
            echo "<td>" . $rowData["text"] . '</td>';

            echo "</tr>";
        }
    }

    $result -> free_result();
}

echo("Error description: " . $mysqli -> error);
$mysqli -> close();
?>

<style>
    tr {
        white-space: nowrap;
    }
    table, th, td {
        border: 1px solid black;
        border-collapse: collapse;
        text-align: left;
        padding-left: 3px;
        padding-right: 3px;
    }
</style>