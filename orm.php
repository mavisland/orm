<?php
class Orm {
    protected $db;
    protected $tablo;

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

    public function __construct($tablo) {
        $this->db = Veritabani::baglan();
        $this->tablo = $tablo;
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->params);

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
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->params);

        $this->resetQuery();
        return (int) $stmt->fetchColumn();
    }

    public function exists() {
        $sql = "SELECT 1 FROM {$this->tablo}";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' ', $this->wheres);
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->params);

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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->params);

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

    public function create(array $veriler) {
        $alanlar = implode(', ', array_keys($veriler));
        $degerler = ':' . implode(', :', array_keys($veriler));
        $sql = "INSERT INTO {$this->tablo} ($alanlar) VALUES ($degerler)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($veriler);
    }

    public function update($id, array $veriler) {
        $set = [];
        foreach ($veriler as $key => $val) {
            $set[] = "$key = :$key";
        }
        $sql = "UPDATE {$this->tablo} SET " . implode(', ', $set) . " WHERE id = :id";
        $veriler['id'] = $id;
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($veriler);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->tablo} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
