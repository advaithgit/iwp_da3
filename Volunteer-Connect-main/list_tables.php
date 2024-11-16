<?php
// Database configuration
$host = 'localhost';
$dbname = 'volunteer-connect-one-org';
$username = 'root';
$password = '';
// Connect to MySQL
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all tables in the database
$sql = "SHOW TABLES";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h1>Database: $dbname</h1>";
    while ($row = $result->fetch_array()) {
        $tableName = $row[0];
        echo "<h2>Table: $tableName</h2>";

        // Fetch table properties
        $tableSql = "DESCRIBE $tableName";
        $tableResult = $conn->query($tableSql);

        if ($tableResult->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                  </tr>";
            
            while ($field = $tableResult->fetch_assoc()) {
                echo "<tr>
                        <td>{$field['Field']}</td>
                        <td>{$field['Type']}</td>
                        <td>{$field['Null']}</td>
                        <td>{$field['Key']}</td>
                        <td>{$field['Default']}</td>
                        <td>{$field['Extra']}</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No fields found for table $tableName.</p>";
        }
    }
} else {
    echo "<p>No tables found in database $dbname.</p>";
}

// Close connection
$conn->close();
?>
