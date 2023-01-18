<?php
/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 01/10/20 02.07
 * @File name           : DB.php
 */

namespace SLiMS;


use Exception;
use Ifsnop\Mysqldump as IMysqldump;
use mysqli;
use PDO;
use PDOException;

class DBMain
{
    /**
     * PDO instance
     * @var PDO|null
     */
    private static ?PDO $instance = null;

    /**
     * MySQLi Instance
     * @var mysqli|null
     */
    private static ?mysqli $instance_mysqli = null;

    /**
     * Intial database instance
     *
     * @param string $driver
     * @throws Exception
     */
    private function __construct(string $driver = 'pdo')
    {
        try {

            if ($driver === 'mysqli') {
                self::$instance_mysqli = new \mysqli(...$this->getProfile($driver));
            } else {
                self::$instance = new PDO(...$this->getProfile($driver));
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, ENVIRONMENT == 'development' ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT);
                self::$instance->query('SET NAMES utf8');
                self::$instance->query('SET CHARACTER SET utf8');
            }

        } catch(PDOException $error) {
            echo $error->getMessage();
        }
    }

    /**
     * A method to get database instance
     * based on database driver PDO | MySQLi
     *
     * @param string $driver
     * @return PDO|mysqli|null
     */
    public static function getInstance(string $driver = 'pdo'): PDO|mysqli|null
    {
        if ($driver === 'mysqli') {
            if (is_null(self::$instance_mysqli)) new DBMain('mysqli');
            return self::$instance_mysqli;
        } else {
            if (is_null(self::$instance)) new DBMain();
            return self::$instance;
        }
    }

    /**
     * Create MySQLDump instance
     * with default profile provide by
     * this Object.
     *
     * @param array $settings
     * @return IMysqldump\Mysqldump
     * @throws Exception
     */
    public static function backup(array $settings = [])
    {
        $static = new static;
        return new IMysqldump\Mysqldump(...array_merge($static->getProfile('pdo'), [$settings]));
    }

    /**
     * Retrive database profile
     * from database.php and
     * return database parameter as splat
     *
     * @param string $driver
     * @return array
     * @throws Exception
     */
    private function getProfile(string $driver = 'pdo'): array
    {
        $config = $this->getConfig();
        $defaultProfile = $config['main_profile'];

        if ($config['proxy']) $defaultProfile = $this->setProxy();

        extract($config['nodes'][$defaultProfile]??[]);

        if (!isset($host)) throw new Exception("Database " . $defaultProfile . " is not valid!");

        // Casting $port as integer
        $port = (int)$port;

        return $driver === 'pdo' ?
                ['mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database, $username, $password]
                :
                [$host, $username, $password, $database, $port];
    }

    /**
     * Get database credential
     *
     * @param string $nodeName
     * @return array
     */
    private function getNode(string $nodeName): array
    {
        return $this->getConfig()['nodes'][$nodeName]??[];
    }

    /**
     * @return array
     */
    private function getConfig(): array
    {
        return require SB . 'config/database.php';
    }

    /**
     * Load proxy validator
     * to manage database connection
     *
     * @return array
     */
    private function setProxy(): array
    {
        if (!file_exists($dbProxy = SB . 'config/database_proxy.php')) return [];
        include $dbProxy;
        return $defaultProfile;
    }
}
