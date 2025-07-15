# 🔥 ORM - Hafif, Sade, Güçlü

Bu sınıf, saf PHP ile yazılmış, framework bağımsız, sade ama güçlü bir ORM yapısıdır.
PDO kullanır, Singleton deseniyle bağlantıyı tek seferde kurar ve Laravel benzeri kullanım kolaylığı sunar.

---

## 🚀 Özellikler

✅ PDO ile güvenli bağlantı
✅ Singleton bağlantı (tek sefer açılır)
✅ `define()` ile kolay yapılandırma
✅ Kolon seçimi, `where()`, `join()`, `groupBy()` gibi SQL kolaylıkları
✅ `save()`, `delete()`, `softDelete()` ve `restore()` gibi model işlemleri
✅ `fillable`, `guarded` ile güvenli veri setleme
✅ `validate()` ile model bazlı doğrulama
✅ `hasMany`, `belongsTo`, `with()` ile ilişkiler
✅ `toArray()`, `toJson()` dönüşümleri
✅ Sayfalama (`paginate()`)

---

## ⚙️ Kurulum

### 1. Veritabanı Yapılandırması

`config.php` gibi bir dosyada tanımlayın:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani');
define('DB_CHARSET', 'utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 2. ORM Sınıfını Dahil Et

```php
require 'config.php';
require 'Orm.php';
```

---

## 🔧 Kullanım

### ✅ Model Tanımı

Her modelin kendi sınıfı olmalı. Örneğin:

```php
class Kullanici extends Orm {
    protected $fillable = ['ad', 'email'];
    protected $tablo = 'kullanicilar';
}
```

> Not: Alternatif olarak constructor ile tablo adı da verilebilir:
>
> `new Orm('kullanicilar')`

---

### 📄 Kayıt Listeleme

```php
$kullanici = new Kullanici();
$veriler = $kullanici->where('aktif', 1)->orderBy('id', 'DESC')->get();
```

### 👤 Tek Kayıt

```php
$kullanici = (new Kullanici())->find(1);
```

### ➕ Yeni Kayıt

```php
$k = new Kullanici();
$k->fill([
    'ad' => 'Tanju',
    'email' => 'tanju@example.com'
]);
$k->save();
```

### ✏️ Güncelleme

```php
$k = (new Kullanici())->find(1);
$k->email = 'yeni@example.com';
$k->save();
```

### ❌ Silme / Soft Delete

```php
$k = (new Kullanici())->find(1);
$k->delete(); // softDelete() özelliği açıksa deleted_at kolonunu günceller
```

---

## 🔁 İlişkiler

### `hasMany`:

```php
class Kullanici extends Orm {
    public function yazilar() {
        return $this->hasMany(Yazi::class, 'kullanici_id');
    }
}
```

### `belongsTo`:

```php
class Yazi extends Orm {
    public function yazar() {
        return $this->belongsTo(Kullanici::class, 'kullanici_id');
    }
}
```

### Eager Loading:

```php
$veriler = (new Yazi())->with('yazar')->get();
```

---

## ✅ Doğrulama (Validation)

```php
class Kullanici extends Orm {
    protected $rules = [
        'ad' => ['required', 'min:3'],
        'email' => ['required', 'email', 'unique']
    ];
}
```

```php
$k = new Kullanici();
$k->fill($_POST);

if ($k->validate()) {
    $k->save();
} else {
    print_r($k->getErrors());
}
```

---

## 🔄 Dönüştürmeler

```php
$k = (new Kullanici())->find(1);

$array = $k->toArray();
$json  = $k->toJson();
```

---

## 📄 Sayfalama

```php
$k = new Kullanici();
$sonuc = $k->where('aktif', 1)->paginate(2, 10); // 2. sayfadan 10 kayıt getir

// $sonuc['data'], $sonuc['toplam'], $sonuc['sayfa_sayisi'] vs.
```

---

## 🎯 Notlar

- Her model sınıfı `Orm` sınıfından türetilmeli
- Model başında tablo adı belirtebilir ya da constructor'da verebilirsin
- `softDelete`, `timestamps`, `fillable`, `guarded` gibi özellikler model özelinde açılıp kapatılabilir

---

## 💬 Katkıda Bulunmak

Bu ORM sınıfı, sade projeler için ideal bir başlangıçtır.
Pull request veya issue ile katkıda bulunmaktan çekinme! 🙌

---

## 🪪 Lisans

MIT License