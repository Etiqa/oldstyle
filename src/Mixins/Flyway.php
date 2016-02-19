<?php
namespace etiqa\Oldstyle\Mixins;


/**
 * Class Flyway
 *
 * Flyway migration tool porting
 *
 * @package etiqa\Oldstyle\Mixins
 */

class Flyway {

    private $databaseConnection = null;
    private $createStatement = "";

    function __construct($databaseConnection, $migration_folder, $flywaySchema) {
        $this->databaseConnection = $databaseConnection;
        $this->folderPath = $migration_folder;
        $this->createStatement = file_get_contents($flywaySchema);
    }


    function getDatabaseConnection() {
        return $this->databaseConnection;
    }

    function setDatabaseConnection($databaseConnection) {
        $this->databaseConnection = $databaseConnection;
    }

    function error($String) {
        echo $String;
    }
    
    function output($string){
        echo "<br>$string";
    }

    function checkTable($connection) {
        $query = $connection->query("SHOW TABLES LIKE 'flyway_schema'");
        $this->output("Checking flyway_schema table");
        if (!$query) {
            $this->output( "Flyway_schema doesn't exist");
            $queryCreateTable = $connection->query($this->createStatement);
             $this->output( "Creating flyway_schema table");
            if (!$queryCreateTable) {
                $this->error("Error creating database");
                $queryCreateTable->free_result();
                return false;
            } else {
                 $this->output("Flyway_schema table created");
                return true;
            }
        } else {
            $this->output( "Flyway_schema exist");
                    $query->free_result();
            return true;
        }
    }

    function executeFile($connection, $file) {
        $this->output( "Reading script $file");
        $statement = file_get_contents($this->folderPath . $file);
        if (!$statement) {
            die('Error opening file');
        } else {
            $this->output( "Executing Script..");  
        
            $query = $connection->multi_query($statement);
            if ($query) {
                 $this->output( "Script successful executed"); 
                do {
                    if ($res = $connection->store_result()) {
                        var_dump($res->fetch_all(MYSQLI_ASSOC));
                        $res->free();
                    }
                } while ($connection->more_results() && $connection->next_result());
                $success = 1;
            } else {
                $this->output( "Script unsuccessful executed"); 
                $success = 0;
                trigger_error('Wrong SQLFile  Error: ' . $connection->errno . ' ' . $connection->error, E_USER_ERROR);
            }
        }

        //$connection->close();
        return $success;
    }

    function last($connection) {
        $this->output( "Reading last script executed"); 
        $version = 0;
        $statements = "SELECT * FROM flyway_schema ORDER BY version_rank DESC LIMIT 1;";
        $result = $connection->query($statements);
        if ($result->num_rows > 0) {
            // output data of each row
            while ($row = $result->fetch_assoc()) {
                $version = $row['version'];
            }
        }
        $result->free_result();
       $this->output( "Last script executed: $version"); 
        return $version;
    }

    public function migrate() {
       $this->output( "Creating connection...");
        $connection = $this->databaseConnection;
        $versions = array();
        if ($this->checkTable($connection)) {

            $directory = new \DirectoryIterator($this->folderPath);
            foreach ($directory as $path => $info) {
                /** @var \SplFileInfo $info */
                $name = $info->getBasename();
                // Ignore dotfiles/folders
                if (substr($name, 0, 1) == '.') {
                    continue;
                }

                if ($info->isDir()) {
                    continue;
                }

                preg_match('/[0-9]{1,9}/',$name,$match);
                $version = intval($match[0]);
                $migrationFiles[$name] = array(
                    'script' => $name,
                    'extension' => $info->getExtension(),
                    'version'=> $version
                );
                $versions[] = $version;
            }
            if(!empty($versions)) {
                array_multisort($versions, $migrationFiles);
                $last = $this->last($connection);
                foreach ($migrationFiles as $key => $file) {
                    if ($file['version'] > $last) {
                        $success = $this->executeFile($connection, $file['script']);
                        $info = $connection->info;
                        if (!$info || $info = "") {
                            $info = "no info";
                        }
                        $this->output("Inserting row schema version {$file['version']} output $success into flyway_schema");

                        $infoStatement = "INSERT INTO flyway_schema VALUES(?,?,?,?,?,?,?,?)";


                        $stmt = $connection->prepare($infoStatement);

                        if (!$stmt) {
                            trigger_error('Wrong SQL: ' . $infoStatement . ' Error: ' . $connection->errno . ' ' . $connection->error, E_USER_ERROR);
                        }

                        $stmt->bind_param("iisssssi", $file['version'], $file['version'], $file['version'], $name, $file['extension'], $file['script'], $info, $success);

                        $stmt->execute() or die(' Error: ' . $connection->errno . ' ' . $connection->error);
                        $this->output("Affected rows flyway_schema: " . $stmt->affected_rows);


                        $stmt->close();
                    }
                }
            }else{
                $this->output("Nothing to migrate");
            }
        }
    }

}
