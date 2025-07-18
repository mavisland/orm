<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani');
define('DB_CHARSET', 'utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');

class Orm {
    protected static $pdo = null;

    protected $db;
    protected $tablo;

    protected $primaryKey = 'id';

    protected $fillable = []; // sadece bunlar set edilir
    protected $guarded = ['id']; // bunlar asla set edilmez (öncelikli)

    protected $wheres = [];
    protected $params = [];
    protected $order = '';
    protected $limit = '';
    protected $offset = '';
    protected $with = [];
    protected $joins = [];
    protected $select = '*';
    protected $groupBy = '';
    protected $having = '';
    protected $distinct = false;

    protected $timestamps = true; // modeller isterse kapatabilir
    protected $createdAtColumn = 'created_at';
    protected $updatedAtColumn = 'updated_at';

    protected $softDelete = false; // model bazlı aktif/pasif
    protected $deletedAtColumn = 'deleted_at';

    protected $rules = [];
    protected $errors = [];

    protected $debug = false;
    protected $queryLog = [];

    public function __construct($tablo) {
        if (!self::$pdo) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                ]);
            } catch (PDOException $e) {
                die("Veritabanı bağlantı hatası: " . $e->getMessage());
            }
        }

        $this->db = self::$pdo;
        $this->tablo = $tablo;
    }

    public function enableDebug(bool $durum = true) {
        if ($durum === null) {
            $durum = (defined('APP_DEBUG') && APP_DEBUG === true);
        }

        $this->debug = $durum;

        return $this;
    }

    protected function logQuery(string $sql, array $params = []) {
        if ($this->debug) {
            $this->queryLog[] = [
                'query' => $sql,
                'params' => $params,
                'time' => date('Y-m-d H:i:s'),
            ];
        }
    }

    protected function runQuery(string $sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $this->logQuery($sql, $params);

        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ($this->debug) {
                error_log("SQL Hatası: " . $e->getMessage() . " - Sorgu: $sql");
            }
            throw $e;
        }

        return $stmt;
    }

    public function getQueryLog() {
        return $this->queryLog;
    }

    public function getLastQuery() {
        return end($this->queryLog);
    }

    public function select($sutunlar) {
        $this->select = $sutunlar;
        return $this;
    }

    public function join($tip, $tablo, $birinciKolon, $operator, $ikinciKolon) {
        $this->joins[] = strtoupper($tip) . " JOIN $tablo ON $birinciKolon $operator $ikinciKolon";
        return $this;
    }

    public function leftJoin($tablo, $birinciKolon, $operator, $ikinciKolon) {
        return $this->join('LEFT', $tablo, $birinciKolon, $operator, $ikinciKolon);
    }

    public function innerJoin($tablo, $birinciKolon, $operator, $ikinciKolon) {
        return $this->join('INNER', $tablo, $birinciKolon, $operator, $ikinciKolon);
    }

    public function rightJoin($tablo, $birinciKolon, $operator, $ikinciKolon) {
        return $this->join('RIGHT', $tablo, $birinciKolon, $operator, $ikinciKolon);
    }

    public function where($kolon, $islem, $deger) {
        $paramKey = ':w_' . count($this->params);
        $this->wheres[] = "$kolon $islem $paramKey";
        $this->params[$paramKey] = $deger;
        return $this;
    }

    public function whereIn($kolon, array $degerler) {
        if (empty($degerler)) {
            // Boş array sorgu anlamı taşımaz
            $this->wheres[] = "0=1";
            return $this;
        }

        $placeholders = [];
        foreach ($degerler as $i => $val) {
            $key = ":w_in_" . count($this->params) + $i;
            $placeholders[] = $key;
            $this->params[$key] = $val;
        }

        $this->wheres[] = "$kolon IN (" . implode(',', $placeholders) . ")";
        return $this;
    }

    public function orWhere($kolon, $islem, $deger) {
        $paramKey = ':w_' . count($this->params);
        if (empty($this->wheres)) {
            $this->wheres[] = "$kolon $islem $paramKey";
        } else {
            $this->wheres[] = "OR $kolon $islem $paramKey";
        }
        $this->params[$paramKey] = $deger;
        return $this;
    }

    public function whereNull($kolon) {
        $this->wheres[] = "$kolon IS NULL";
        return $this;
    }

    public function whereNotNull($kolon) {
        $this->wheres[] = "$kolon IS NOT NULL";
        return $this;
    }

    public function groupBy($sutun) {
        $this->groupBy = " GROUP BY $sutun";
        return $this;
    }

    public function having($kosul) {
        $this->having = " HAVING $kosul";
        return $this;
    }

    public function distinct($durum = true) {
        $this->distinct = $durum;
        return $this;
    }

    public function orderBy($kolon, $yon = 'ASC') {
        $this->order = " ORDER BY $kolon $yon";
        return $this;
    }

    public function limit($adet, $baslangic = null) {
        $this->limit = " LIMIT " . ($baslangic !== null ? "$baslangic, " : "") . "$adet";
        return $this;
    }

    protected function loadRelation(array &$models, string $relation) {
        if (empty($models)) return;

        $relatedModels = [];
        $relatedModelClass = null;

        // İlk nesneden ilişkili modeli al
        $firstModel = $models[0];

        if (!method_exists($firstModel, $relation)) {
            throw new Exception("Relation method '$relation' not found on " . get_class($firstModel));
        }

        // İlk model üzerinden ilişkili model classını alıyoruz
        $relatedSample = $firstModel->$relation();

        if (is_array($relatedSample)) {
            // hasMany
            $relatedModelClass = get_class($relatedSample[0] ?? null);
        } else {
            // belongsTo veya single model
            $relatedModelClass = get_class($relatedSample);
        }

        if (!$relatedModelClass) return;

        // Ana modelin localKey alanlarını topla
        $localKeys = array_map(fn($m) => $m->{$this->getLocalKey($relation)} ?? null, $models);
        $localKeys = array_filter($localKeys);

        if (empty($localKeys)) return;

        // İlişkili modelde foreignKey ile toplu sorgu yap
        $relatedModel = new $relatedModelClass();

        // İlişki detaylarını model metodundan alalım:
        // Burada convention (varsayılan) kullanalım. Dilersen genişletiriz.
        $foreignKey = $this->getForeignKey($relation);

        $relatedItems = $relatedModel->whereIn($foreignKey, $localKeys)->get();

        // İlişki tipine göre eşle
        if (is_array($relatedSample)) {
            // hasMany: her ana modele ilişkili birden çok nesne dizisi bağla
            foreach ($models as $model) {
                $model->{$relation} = array_filter($relatedItems, fn($item) => $item->{$foreignKey} == $model->{$this->getLocalKey($relation)});
            }
        } else {
            // belongsTo: tek nesne bağla
            foreach ($models as $model) {
                $model->{$relation} = current(array_filter($relatedItems, fn($item) => $item->{$this->getLocalKey($relation)} == $item->{$foreignKey})) ?: null;
            }
        }
    }

    public function get() {
        $select = $this->distinct ? "DISTINCT {$this->select}" : $this->select;

        $sql = "SELECT {$select} FROM {$this->tablo}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' ', $this->wheres);
        }

        $sql .= $this->groupBy;
        $sql .= $this->having;
        $sql .= $this->order;
        $sql .= $this->limit;

        $stmt = $this->runQuery($sql, $this->params);

        $sonuclar = $stmt->fetchAll(PDO::FETCH_CLASS, get_class($this)); // model nesnesi olarak alıyoruz

        // Eğer with() çağrıldıysa ilişkili modelleri yükle
        if (!empty($this->with)) {
            foreach ($this->with as $relation) {
                $this->loadRelation($sonuclar, $relation);
            }
        }

        $this->resetQuery();

        return $sonuclar;
    }

    public function first() {
        $this->limit(1);
        $sonuc = $this->get();
        return $sonuc[0] ?? null;
    }

    public function resetQuery() {
        $this->wheres = [];
        $this->params = [];
        $this->order = '';
        $this->limit = '';
        $this->offset = '';
        $this->joins = [];
        $this->select = '*';
        $this->with = [];
        $this->groupBy = '';
        $this->having = '';
        $this->distinct = false;
    }

    public function find($id) {
        $sql = "SELECT * FROM {$this->tablo} WHERE id = :id LIMIT 1";
        $stmt = $this->runQuery($sql, ['id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function all() {
        $sql = "SELECT * FROM {$this->tablo}";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_OBJ);
    }

    public function count() {
        $sql = "SELECT COUNT(*) as toplam FROM {$this->tablo}";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' ', $this->wheres);
        }

        $stmt = $this->runQuery($sql, $this->params);

        $this->resetQuery();
        return (int) $stmt->fetchColumn();
    }

    public function exists() {
        $sql = "SELECT 1 FROM {$this->tablo}";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' ', $this->wheres);
        }
        $sql .= " LIMIT 1";

        $stmt = $this->runQuery($sql, $this->params);

        $this->resetQuery();
        return (bool) $stmt->fetchColumn();
    }

    public function pluck($sutun) {
        $sql = "SELECT {$sutun} FROM {$this->tablo}";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' ', $this->wheres);
        }
        $sql .= $this->order;
        $sql .= $this->limit;

        $stmt = $this->runQuery($sql, $this->params);

        $this->resetQuery();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function firstPluck($sutun) {
        $this->limit(1);
        $sonuc = $this->pluck($sutun);
        return $sonuc[0] ?? null;
    }

    public function hasMany($modelClass, $foreignKey, $localKey = 'id') {
        $localValue = $this->{$localKey} ?? null;

        if ($localValue === null) {
            return [];
        }

        $model = new $modelClass();

        return $model->where($foreignKey, '=', $localValue)->get();
    }

    public function belongsTo($modelClass, $foreignKey, $ownerKey = 'id') {
        $foreignValue = $this->{$foreignKey} ?? null;

        if ($foreignValue === null) {
            return null;
        }

        $model = new $modelClass();

        return $model->where($ownerKey, '=', $foreignValue)->first();
    }

    public function with(...$relations) {
        $this->with = $relations;

        return $this;
    }

    public function paginate($sayfa = 1, $adet = 10) {
        $sayfa = max(1, (int)$sayfa);
        $adet = max(1, (int)$adet);
        $offset = ($sayfa - 1) * $adet;

        // Toplam kayıt sayısını hesapla
        $sayac = clone $this;
        $toplam = $sayac->count();

        // Sayfalı veriyi çek
        $this->limit($adet, $offset);
        $veriler = $this->get();

        return [
            'data' => $veriler,
            'toplam' => $toplam,
            'sayfa' => $sayfa,
            'sayfa_sayisi' => ceil($toplam / $adet),
            'adet' => $adet
        ];
    }

    protected function isFillable($alan) {
        if (!empty($this->guarded) && in_array($alan, $this->guarded)) {
            return false;
        }

        if (!empty($this->fillable)) {
            return in_array($alan, $this->fillable);
        }

        return true; // ikisi de boşsa her şey set edilebilir
    }

    public function fill(array $veriler) {
        foreach ($veriler as $alan => $deger) {
            if ($this->isFillable($alan)) {
                $this->$alan = $deger;
            }
        }

        return $this;
    }

    protected function triggerEvent(string $event) {
        if (method_exists($this, $event)) {
            $this->$event();
        }
    }

    public function save() {
        $this->triggerEvent('beforeSave');

        $veriler = get_object_vars($this);

        // iç özellikleri temizle
        unset($veriler['db'], $veriler['tablo'], $veriler['joins'], $veriler['wheres'],
            $veriler['params'], $veriler['order'], $veriler['limit'], $veriler['offset'],
            $veriler['with'], $veriler['select'], $veriler['groupBy'], $veriler['having'],
            $veriler['distinct'], $veriler['primaryKey'], $veriler['timestamps'],
            $veriler['createdAtColumn'], $veriler['updatedAtColumn']);

        $id = $veriler[$this->primaryKey] ?? null;

        $simdi = date('Y-m-d H:i:s');

        if ($id) {
            // Güncelleme
            if ($this->timestamps && property_exists($this, $this->updatedAtColumn)) {
                $this->{$this->updatedAtColumn} = $simdi;
                $veriler[$this->updatedAtColumn] = $simdi;
            }

            $verilerKopya = $veriler;
            unset($verilerKopya[$this->primaryKey]);

            $set = [];
            foreach ($verilerKopya as $key => $val) {
                $set[] = "$key = :$key";
            }

            $sql = "UPDATE {$this->tablo} SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = :{$this->primaryKey}";
            $stmt = $this->runQuery($sql, $veriler);

            $this->triggerEvent('afterSave');

            return $sonuc;
        } else {
            // Yeni kayıt
            if ($this->timestamps) {
                $this->{$this->createdAtColumn} = $simdi;
                $this->{$this->updatedAtColumn} = $simdi;
                $veriler[$this->createdAtColumn] = $simdi;
                $veriler[$this->updatedAtColumn] = $simdi;
            }

            $alanlar = implode(', ', array_keys($veriler));
            $degerler = ':' . implode(', :', array_keys($veriler));

            $sql = "INSERT INTO {$this->tablo} ($alanlar) VALUES ($degerler)";
            $basarili = $this->runQuery($sql, $veriler);

            if ($basarili) {
                $this->{$this->primaryKey} = $this->db->lastInsertId();
            }

            $this->triggerEvent('afterSave');

            return $basarili;
        }
    }

    public function create(array $veriler) {
        $alanlar = implode(', ', array_keys($veriler));
        $degerler = ':' . implode(', :', array_keys($veriler));
        $sql = "INSERT INTO {$this->tablo} ($alanlar) VALUES ($degerler)";
        return $this->runQuery($sql, $veriler);
    }

    public function update($id, array $veriler) {
        $set = [];

        foreach ($veriler as $key => $val) {
            $set[] = "$key = :$key";
        }

        $sql = "UPDATE {$this->tablo} SET " . implode(', ', $set) . " WHERE id = :id";
        $veriler['id'] = $id;

        return $this->runQuery($sql, $veriler);
    }

    public function delete() {
        if ($this->softDelete) {
            return $this->softDelete();
        }

        return $this->forceDelete();
    }

    public function softDelete() {
        if (!$this->softDelete) {
            throw new Exception("Soft delete özelliği bu modelde aktif değil.");
        }

        $this->triggerEvent('beforeDelete');

        $kolon = $this->deletedAtColumn;
        $zaman = date('Y-m-d H:i:s');

        $sql = "UPDATE {$this->tablo} SET $kolon = :zaman WHERE {$this->primaryKey} = :id";
        $sonuc = $this->runQuery($sql, [
            ':zaman' => $zaman,
            ':id' => $this->{$this->primaryKey}
        ]);

        $this->triggerEvent('afterDelete');

        return $sonuc;
    }

    public function forceDelete() {
        $this->triggerEvent('beforeDelete');

        $sql = "DELETE FROM {$this->tablo} WHERE {$this->primaryKey} = :id";
        $sonuc = $this->runQuery($sql, [
            ':id' => $this->{$this->primaryKey}
        ]);

        $this->triggerEvent('afterDelete');

        return $sonuc;
    }

    public function restore() {
        if (!$this->softDelete) {
            throw new Exception("Restore özelliği bu modelde aktif değil.");
        }

        $kolon = $this->deletedAtColumn;

        $sql = "UPDATE {$this->tablo} SET $kolon = NULL WHERE {$this->primaryKey} = :id";

        return $this->runQuery($sql, [
            ':id' => $this->{$this->primaryKey}
        ]);
    }

    public function onlyTrashed() {
        return $this->whereNotNull($this->deletedAtColumn);
    }

    public function withTrashed() {
        // get()'e otomatik WHERE koymak yerine tamamen serbest bırakıyoruz
        return $this; // future flag eklenebilir
    }

    public function toArray() {
        $veriler = get_object_vars($this);

        // Dahili alanları ayıklıyoruz
        unset($veriler['db'], $veriler['tablo'], $veriler['joins'], $veriler['wheres'],
            $veriler['params'], $veriler['order'], $veriler['limit'], $veriler['offset'],
            $veriler['with'], $veriler['select'], $veriler['groupBy'], $veriler['having'],
            $veriler['distinct'], $veriler['primaryKey'], $veriler['softDelete'],
            $veriler['timestamps'], $veriler['createdAtColumn'], $veriler['updatedAtColumn'],
            $veriler['deletedAtColumn']);

        // Eager-loaded ilişkileri kontrol et
        foreach ($this->with as $relation) {
            if (isset($this->$relation)) {
                $ilişkili = $this->$relation;

                if (is_array($ilişkili)) {
                    $veriler[$relation] = array_map(fn($item) => $item instanceof self ? $item->toArray() : $item, $ilişkili);
                } elseif ($ilişkili instanceof self) {
                    $veriler[$relation] = $ilişkili->toArray();
                } else {
                    $veriler[$relation] = $ilişkili;
                }
            }
        }

        return $veriler;
    }


    public static function collectionToArray(array $liste) {
        return array_map(fn($item) => $item->toArray(), $liste);
    }

    public function toJson($options = 0) {
        return json_encode($this->toArray(), $options);
    }

    public static function collectionToJson(array $liste, $options = 0) {
        return json_encode(self::collectionToArray($liste), $options);
    }

    protected function validateRequired($alan, $deger, $params) {
        if ($deger === null || $deger === '') {
            $this->errors[$alan][] = "$alan alanı zorunludur.";
        }
    }

    protected function validateEmail($alan, $deger, $params) {
        if ($deger && !filter_var($deger, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$alan][] = "$alan geçerli bir e-posta olmalıdır.";
        }
    }

    protected function validateMin($alan, $deger, $params) {
        if (is_numeric($deger) && $deger < (int)$params) {
            $this->errors[$alan][] = "$alan alanı minimum $params değerinde olmalıdır.";
        } elseif (is_string($deger) && mb_strlen($deger) < (int)$params) {
            $this->errors[$alan][] = "$alan alanı minimum $params karakter olmalıdır.";
        }
    }

    protected function validateInteger($alan, $deger, $params) {
        if ($deger !== null && !filter_var($deger, FILTER_VALIDATE_INT)) {
            $this->errors[$alan][] = "$alan alanı tamsayı olmalıdır.";
        }
    }

    protected function validateRegex($alan, $deger, $params) {
        if ($deger && !preg_match($params, $deger)) {
            $this->errors[$alan][] = "$alan alanı geçerli formatta değil.";
        }
    }

    protected function validateUnique($alan, $deger, $params) {
        $sql = "SELECT COUNT(*) FROM {$this->tablo} WHERE $alan = :deger";

        // Güncelleme sırasında kendi kaydını saymamak için id kontrolü
        if (isset($this->{$this->primaryKey})) {
            $sql .= " AND {$this->primaryKey} != :id";
        }

        $paramsArr = [':deger' => $deger];

        if (isset($this->{$this->primaryKey})) {
            $paramsArr[':id'] = $this->{$this->primaryKey};
        }

        $this->runQuery($sql, $paramsArr);

        if ($stmt->fetchColumn() > 0) {
            $this->errors[$alan][] = "$alan zaten kullanılıyor.";
        }
    }

    protected function validateCallback($alan, $deger, $params) {
        if (method_exists($this, $params)) {
            $this->$params($alan, $deger);
        } else {
            $this->errors[$alan][] = "Geçersiz callback fonksiyon: $params";
        }
    }

    public function validate() {
        $this->errors = [];

        foreach ($this->rules as $alan => $kurallar) {
            $deger = $this->$alan ?? null;

            foreach ($kurallar as $kural) {
                $params = null;

                if (strpos($kural, ':') !== false) {
                    [$kural, $params] = explode(':', $kural, 2);
                }

                $method = 'validate' . ucfirst($kural);

                if (method_exists($this, $method)) {
                    $this->$method($alan, $deger, $params);
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }
}
