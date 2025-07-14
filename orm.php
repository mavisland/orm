<?php
class Orm {
    protected $db;
    protected $tablo;

    public function __construct($tablo) {
        $this->db = Veritabani::baglan();
        $this->tablo = $tablo;
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
