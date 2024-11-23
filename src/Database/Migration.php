<?php

namespace Forge\Database;

use Forge\Console\Logger;

class Migration
{
    protected $db;
    protected $logger;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger(); // Instancier le Logger
        $this->initializeMigrationHistory();
    }

    protected function execute($sql): void
    {
        $this->db->exec($sql);
    }

    /**
     * Initialise la table d'historique des migrations.
     */
    private function initializeMigrationHistory(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations_history(
            `id` INT NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL,
            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        )";
        $this->execute($sql);
    }

    /**
     * Supprime la table migration history.
     */
    private function dropMigrationHistory(): void
    {
        $sql = "DROP TABLE IF EXISTS migrations_history;";
        $this->execute($sql);
    }

    /**
     * Vérifie si une migration a déjà été exécutée.
     */
    private function isMigrationExecuted(string $migration): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations_history WHERE migration = :migration");
        $stmt->execute(['migration' => $migration]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Marque une migration comme exécutée.
     */
    private function markMigrationAsExecuted(string $migration): void
    {
        $stmt = $this->db->prepare("INSERT INTO migrations_history (migration) VALUES (:migration)");
        $stmt->execute(['migration' => $migration]);
    }

    /**
     * Supprime une migration de l'historique des migrations exécutées.
     */
    private function deleteMigrationAsExecuted(string $migration): void
    {
        $stmt = $this->db->prepare("DELETE FROM migrations_history WHERE migration = :migration");
        $stmt->execute(['migration' => $migration]);
    }

    /**
     * Affiche l'état des migrations (status).
     */
    public function showMigrationStatus(): void
    {
        $stmt = $this->db->query("SELECT migration, executed_at FROM migrations_history ORDER BY executed_at");
        $results = $stmt->fetchAll();

        $this->logger->info("Status des migrations :");
        if (empty($results)) {
            $this->logger->info("Aucune migration exécutée.");
        } else {
            foreach ($results as $row) {
                $this->logger->info("- {$row['migration']} exécutée le {$row['executed_at']}");
            }
        }
    }

    /**
     * Exécute les migrations.
     */
    public function migrate($type = "run")
    {
        foreach (glob(__DIR__ . '/../../database/migrations/*.php') as $migrationFile) {
            $migrationInstance = require $migrationFile;

            try {
                if (is_object($migrationInstance)) {
                    $migrationName = basename($migrationFile, '.php');

                    switch ($type) {
                        case "run":
                            if (!$this->isMigrationExecuted($migrationName)) {
                                $this->logger->info("Exécution des migrations...");
                                $migrationInstance->up();
                                $this->markMigrationAsExecuted($migrationName);
                            }
                            break;

                        case "rollback":
                            if ($this->isMigrationExecuted($migrationName)) {
                                $this->logger->info("Rollback de la migration...");
                                $migrationInstance->down();
                                $this->deleteMigrationAsExecuted($migrationName);
                            }
                            break;

                        case "refresh":
                            if ($this->isMigrationExecuted($migrationName)) {
                                $this->logger->info("Refresh de la migration...");
                                $migrationInstance->down();
                                $this->deleteMigrationAsExecuted($migrationName);
                            }
                            $migrationInstance->up();
                            $this->markMigrationAsExecuted($migrationName);
                            break;

                        case "fresh":
                            $this->logger->info("Fresh migration...");
                            $this->dropMigrationHistory();
                            $migrationInstance->down();
                            $this->initializeMigrationHistory();
                            $migrationInstance->up();
                            $this->markMigrationAsExecuted($migrationName);
                            break;

                        case "down":
                            $this->logger->info("Suppression de toutes les migrations...");
                            $this->dropMigrationHistory();
                            $migrationInstance->down();
                            break;

                        case "reset":
                            $this->logger->info("Réinitialisation des migrations...");
                            if ($this->isMigrationExecuted($migrationName)) {
                                $migrationInstance->down();
                                $this->deleteMigrationAsExecuted($migrationName);
                            }
                            $migrationInstance->up();
                            $this->markMigrationAsExecuted($migrationName);
                            break;

                        case "status":
                            $this->showMigrationStatus();
                            break;

                        default:
                            $this->logger->error("Commande non reconnue : $type");
                            break;
                    }

                    $this->logger->success("Migration terminée : " . $this->logger->bold($migrationName));
                } else {
                    $this->logger->error("Migration non trouvée ou non valide dans $migrationFile.");
                }
            } catch (\Exception $e) {
                $this->logger->error("Erreur lors de l'exécution de la migration $migrationFile : " . $e->getMessage());
            }
        }
    }
}
