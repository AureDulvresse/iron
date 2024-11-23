<?php

namespace Forge\Database\Iron;

use Forge\Database\Iron\Relationship\HasManyRelationship;
use Forge\Database\Iron\Relationship\HasOneRelationship;
use Forge\Database\Iron\Relationship\ManyToManyRelationship;
use PDO;
use Forge\Database\Database;
use Forge\Support\Helpers\Str;
use Exception;
use Forge\Database\Iron\Relationship\BelongsToRelationship;

class Model
{
    protected static $table; // Nom de la table

    protected $db; // Connexion à la base de données
    protected $sql; // Requête SQL
    protected $params = []; // Paramètres pour la requête
    protected $attributes = []; // Attributs du modèle
    protected $fillable = []; // Colonnes modifiables
    protected $id; // ID de l'enregistrement

    public function __construct(array $attributes = [])
    {
        $this->db = Database::getInstance()->getConnection(); // Connexion à la base de données

        // Initialiser les attributs
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key) || in_array($key, $this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }

        // Définir la table si non spécifiée
        if (!isset(static::$table)) {
            static::$table = strtolower(class_basename(static::class)) . 's';
        }
    }

    public function getID(): int
    {
        return $this->id;
    }

    // Méthode getter pour accéder à la connexion
    public function getDb()
    {
        return $this->db;
    }

    // ----- CRUD Operations -----

    // Créer un nouvel enregistrement
    public static function create(array $data): Model
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_map(fn($col) => ":$col", array_keys($data)));

        $sql = "INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)";
        $stmt = (new static())->db->prepare($sql);
        if (isset($data['created_at'])) {
            $data['created_at'] = Str::formatDateToString(new \DateTime());
        }

        if (isset($data['updated_at'])) {
            $data['updated_at'] = Str::formatDateToString(new \DateTime());
        }
        $stmt->execute($data);

        // Récupérer l'ID de l'enregistrement nouvellement créé
        $data['id'] = (new static())->db->lastInsertId();
        return (new static())->populate($data);
    }

    // Récupérer tous les enregistrements
    public static function all(): mixed
    {
        $sql = "SELECT * FROM " . static::$table;
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    // Récupérer un enregistrement via l'id
    public static function find($id): mixed
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE id = :id";
        $stmt = (new static())->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetchObject(static::class);
    }

    // Mettre à jour un enregistrement
    public static function update($id, $data): mixed
    {
        $fields = implode(", ", array_map(fn($col) => "$col = :$col", array_keys($data)));

        $sql = "UPDATE " . static::$table . " SET $fields WHERE id = :id";
        $stmt = (new static())->db->prepare($sql);
        $data['id'] = $id;
        return $stmt->execute($data);
    }

    // Supprimer un enregistrement
    public static function delete($id): mixed
    {
        $sql = "DELETE FROM " . static::$table . " WHERE id = :id";
        $stmt = (new static())->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    // ----- Filtering and Counting -----

    // Compter les enregistrements
    public static function count(): mixed
    {
        $sql = "SELECT COUNT(*) FROM " . static::$table;
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchColumn();
    }

    // Pagination
    public static function paginate(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM " . static::$table . " LIMIT :limit OFFSET :offset";
        $stmt = (new static())->db->prepare($sql);
        $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    public static function first(): mixed
    {
        $sql = "SELECT * FROM " . static::$table . " LIMIT 1";
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchObject(static::class);
    }

    public static function exists(array $conditions): bool
    {
        $whereClause = implode(" AND ", array_map(fn($col) => "$col = :$col", array_keys($conditions)));
        $sql = "SELECT COUNT(*) FROM " . static::$table . " WHERE $whereClause";
        $stmt = (new static())->db->prepare($sql);
        $stmt->execute($conditions);
        return (bool)$stmt->fetchColumn();
    }

    public static function latest(string $column = 'created_at'): mixed
    {
        $sql = "SELECT * FROM " . static::$table . " ORDER BY $column DESC LIMIT 1";
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchObject(static::class);
    }

    public static function oldest(string $column = 'created_at'): mixed
    {
        $sql = "SELECT * FROM " . static::$table . " ORDER BY $column ASC LIMIT 1";
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchObject(static::class);
    }

    public static function pluck(string $column): array
    {
        $sql = "SELECT $column FROM " . static::$table;
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        do {
            $sql = "SELECT * FROM " . static::$table . " LIMIT $size OFFSET $offset";
            $stmt = (new static())->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_CLASS, static::class);

            if (empty($results)) {
                break;
            }

            $callback($results);
            $offset += $size;
        } while (count($results) === $size);
    }

    public static function increment($id, string $column, int $amount = 1): bool
    {
        $sql = "UPDATE " . static::$table . " SET $column = $column + :amount WHERE id = :id";
        $stmt = (new static())->db->prepare($sql);
        return $stmt->execute(['amount' => $amount, 'id' => $id]);
    }

    public static function decrement($id, string $column, int $amount = 1): bool
    {
        $sql = "UPDATE " . static::$table . " SET $column = $column - :amount WHERE id = :id";
        $stmt = (new static())->db->prepare($sql);
        return $stmt->execute(['amount' => $amount, 'id' => $id]);
    }

    public static function withTrashed(): array
    {
        $sql = "SELECT * FROM " . static::$table;
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    public static function onlyTrashed(): array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE deleted_at IS NOT NULL";
        $stmt = (new static())->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    public static function restore($id): bool
    {
        $sql = "UPDATE " . static::$table . " SET deleted_at = NULL WHERE id = :id";
        $stmt = (new static())->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    // Filtrer les enregistrements
    public static function filter(array $conditions): mixed
    {
        $whereClause = implode(" AND ", array_map(fn($col) => "$col = :$col", array_keys($conditions)));
        $sql = "SELECT * FROM " . static::$table . " WHERE $whereClause";
        $stmt = (new static())->db->prepare($sql);
        $stmt->execute($conditions);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    // Relationship

    // Méthode pour définir une relation "BelongsToMany"
    public function belongsTo(string $relatedModel, string $foreignKey): BelongsToRelationship
    {
        return new BelongsToRelationship($this, $relatedModel, $foreignKey);
    }

    // Méthode pour définir une relation "ManyToMany"
    public function belongsToMany(string $relatedModel, string $pivotTable, string $foreignKey, string $relatedKey): ManyToManyRelationship
    {
        return new ManyToManyRelationship($this, $relatedModel, $pivotTable, $foreignKey, $relatedKey);
    }

    // Méthode pour définir une relation "HasOne"
    public function hasOne(string $relatedModel, string $foreignKey): HasOneRelationship
    {
        return new HasOneRelationship($this, $relatedModel, $foreignKey);
    }

    // Méthode pour définir une relation "HasMany"
    public function hasMany(string $relatedModel, string $foreignKey): HasManyRelationship
    {
        return new HasManyRelationship($this, $relatedModel, $foreignKey);
    }

    // ----- Query Builder -----

    // Méthode pour créer une nouvelle instance de QueryBuilder
    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::$table);
    }

    // Méthode where pour ajouter des conditions
    public function where(string $column, string $operator = '=', mixed $value): static
    {
        // Ajoute la condition à la requête
        $this->sql .= " WHERE $column $operator :$column";
        $this->params[$column] = $value; // Ajoute le paramètre
        return $this; // Retourne l'instance pour chaînage
    }

    // ----- Save and Populate -----

    // Méthode pour enregistrer ou mettre à jour l'enregistrement
    public function save(): bool
    {
        $data = $this->toArray(); // Obtenez les données sous forme de tableau

        if (isset($this->id)) {
            // Mettre à jour l'enregistrement existant
            return static::update($this->id, $data);
        } else {
            // Créer un nouvel enregistrement
            $newInstance = static::create($data);
            // Peupler l'instance actuelle avec les données nouvellement créées
            $this->populate((array)$newInstance);
            return true;
        }
    }

    // ----- Gestion des factories -----

    public static function factory(int $count = 1)
    {
        $factoryClass = 'Database\\Factories\\' . class_basename(static::class) . 'Factory';

        if (!class_exists($factoryClass)) {
            throw new Exception("Factory class $factoryClass does not exist.");
        }

        $factory = new $factoryClass(static::class);
        return $factory->count($count);
    }

    public static function form()
    {
        $formClass = 'App\\Forms\\' . class_basename(static::class) . 'Form';

        if (!class_exists($formClass)) {
            throw new Exception("Form class $formClass does not exist.");
        }

        $form = new $formClass(static::class);
        return $form;
    }
    
    // Convertir l'objet en tableau
    protected function toArray(): array
    {
        return $this->attributes;
    }

    // Peupler les propriétés de l'objet
    protected function populate($data): static
    {
        $this->attributes = $data;
        return $this; // Retourner l'instance courante
    }

}
