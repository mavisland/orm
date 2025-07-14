<?php
class Orm {
    protected $db;
    protected $tablo;

    protected $wheres = [];
    protected $params = [];
    protected $order = '';
    protected $limit = '';
    protected $offset = '';

    public function __construct($tablo) {
        $this->db = Veritabani::baglan();
        $this->tablo = $tablo;
    }

    public function where($kolon, $islem, $deger) {
        $paramKey = ':w_' . count($this->params);
        $this->wheres[] = "$kolon $islem $paramKey";
        $this->params[$paramKey] = $deger;
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

    public function orderBy($kolon, $yon = 'ASC') {
        $this->order = " ORDER BY $kolon $yon";
        return $this;
    }

    public function limit($adet, $baslangic = null) {
        $this->limit = " LIMIT " . ($baslangic !== null ? "$baslangic, " : "") . "$adet";
        return $this;
    }

    public function get() {
        $sql = "SELECT * FROM {$this->tablo}";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' ', $this->wheres);
        }
        $sql .= $this->order;
        $sql .= $this->limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->params);

        $this->resetQuery(); // bir sonraki sorgu için temizlik
        return $stmt->fetchAll(PDO::FETCH_OBJ);
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
