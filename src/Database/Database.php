<?php

namespace Forge\Database;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;

    // Types de bases de données supportés
    private const DB_DRIVERS = [
        'mysql' => 'mysql',
        'pgsql' => 'pgsql',
        'sqlite' => 'sqlite'
    ];

    private function __construct()
    {
        // Charger les variables d'environnement
        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';  // Valeur par défaut : MySQL
        $host = $_ENV['DB_HOST'] ?? '';
        $name = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'] ?? null;
        $pass = $_ENV['DB_PASS'] ?? null;
        $charset = 'utf8';

        // Connexion en fonction du type de base de données
        try {
            switch ($driver) {
                case self::DB_DRIVERS['mysql']:
                    $this->connection = new PDO(
                        "mysql:host=$host;dbname=$name;charset=$charset",
                        $user,
                        $pass
                    );
                    break;

                case self::DB_DRIVERS['pgsql']:
                    $this->connection = new PDO(
                        "pgsql:host=$host;dbname=$name",
                        $user,
                        $pass
                    );
                    break;

                case self::DB_DRIVERS['sqlite']:
                    // SQLite n'a pas besoin de host
                    $this->connection = new PDO("sqlite:$name");
                    break;

                default:
                    throw new PDOException("Driver de base de données non supporté.");
            }

            // Définir les attributs PDO
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    // Méthode pour récupérer l'instance de la base de données
    public static function getInstance(): mixed
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Méthode pour récupérer la connexion PDO
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
