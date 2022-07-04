<?php

$host = "localhost";

// Note: should be secured against SQL injection

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli("arg-tech-aifdb_mysql:3306", "root", "@rgtech", "aifdb");

echo $mysqli->host_info . "\n";

$subdiagram = array("L", "TA", "L", "YA", "I", "RA", "I", "CA", "I");
//$subdiagram = array("L", "TA");
$subdiagramText = array("NULL", "NULL", "NULL", "NULL", "NULL", "NULL", "NULL", "NULL", "NULL");

$t = microtime(TRUE);

$queryPart1 = "
    CREATE TEMPORARY TABLE temp_table_n1 (
	nodeID int(10),
    text longtext,
    type varchar(2),
    nodeSetID int(10),
    history longtext);
        ";
$mysqli -> query($queryPart1);

$queryPart2 = "
    CREATE TEMPORARY TABLE temp_table_n2 as (
	SELECT * FROM temp_table_n1
	LIMIT 0);
        ";
$mysqli -> query($queryPart2);

$queryPart3 = "
    INSERT INTO temp_table_n1 
    SELECT n2.nodeID, n2.text, n2.type, nm1.nodeSetID, CONCAT(n1.nodeID, '-', n1.type, ' < ', n2.nodeID, '-', n2.type)
    FROM
	edges e1
    JOIN nodes n1 ON (
        (n1.nodeID = e1.fromID) AND 
        (n1.type = \"$subdiagram[0]\") AND
        (n1.text = \"$subdiagramText[0]\" OR \"$subdiagramText[0]\" = \"NULL\"))
    JOIN nodes n2 ON (
        (n2.nodeID = e1.toID) AND 
        (n2.type = \"$subdiagram[1]\") AND
        (n2.text = \"$subdiagramText[1]\" OR \"$subdiagramText[1]\" = \"NULL\"))
    JOIN nodeSetMappings nm1 ON (nm1.nodeID = n1.nodeID)
    JOIN nodeSetMappings nm2 ON (nm2.nodeID = n2.nodeID)
    WHERE (nm1.nodeSetID = nm2.nodeSetID)
    GROUP BY n2.nodeID, n2.text, n2.type, nm1.nodeSetID, n1.nodeID;
    ";
$mysqli -> query($queryPart3);

$numOfNodes = count($subdiagram);
for ($i = 1; $i < $numOfNodes - 1; $i++) {
//for ($i = 1; $i < 8; $i++) {
    $j = $i + 1;

    $queryPart4 = "
        INSERT INTO temp_table_n2
        SELECT n2.nodeID, n2.text, n2.type, nm.nodeSetID, CONCAT(n1.history, ' < ', n2.nodeID, '-', n2.type)
        FROM
	    edges e1
        JOIN temp_table_n1 n1 ON (
            (n1.nodeID = e1.fromID) AND 
            (n1.type = \"$subdiagram[$i]\") AND
            (n1.text = \"$subdiagramText[$i]\" OR \"$subdiagramText[$i]\" = \"NULL\"))
        JOIN nodes n2 ON (
            (n2.nodeID = e1.toID) AND 
            (n2.type = \"$subdiagram[$j]\") AND
            (n2.text = \"$subdiagramText[$j]\" OR \"$subdiagramText[$j]\" = \"NULL\"))
        -- DEBUG: JOIN nodeSetMappings nm ON (n2.nodeID = nm.nodeID)
        -- DEBUG: WHERE nm.nodeSetID = 17874
        JOIN nodeSetMappings nm ON (nm.nodeID = n2.nodeID)
        WHERE (nm.nodeSetID = n1.nodeSetID)
        GROUP BY n2.nodeID, n2.text, n2.type, nm.nodeSetID, n1.history;
        ";
    $mysqli -> query($queryPart4);

    $mysqli -> query("TRUNCATE TABLE temp_table_n1;");
    $mysqli -> query("INSERT INTO temp_table_n1 SELECT * FROM temp_table_n2;");
    $mysqli -> query("TRUNCATE TABLE temp_table_n2;");
}

$queryGetResults = "
    SELECT n1.nodeID, n1.text, n1.type, n1.nodeSetID, n1.history
    FROM temp_table_n1 n1;
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