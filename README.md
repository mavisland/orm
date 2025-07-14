# ORM

PHP ile saf PDO kullanarak geliştirilmiş, minimal ama güçlü bir ORM (Object-Relational Mapper) kütüphanesi.

## Özellikler

- Basit ve anlaşılır fluent API
- Otomatik insert / update işlemleri (`save()` metodu)
- Soft delete ve restore destekleri
- `hasMany` ve `belongsTo` ilişkileri
- Eager loading (`with()`)
- Query builder: `where()`, `orWhere()`, `join()`, `groupBy()`, `having()`, `orderBy()`, `limit()`
- Sayfalama desteği (`paginate()`)
- Veri doğrulama (`validate()` ve kurallar)
- Fillable ve guarded alanlar ile güvenli veri setleme
- JSON ve dizi dönüşümleri (`toJson()`, `toArray()`)
- Debug modu ile sorgu loglama
- Prepared statements ile güvenli sorgular

## Kurulum

ORM saf PHP ile yazılmıştır. Tek ihtiyacınız PDO ile bağlantı sağlayan bir `Veritabani::baglan()` fonksiyonudur.

```php
// Örnek veritabanı bağlantısı
class Veritabani {
    public static function baglan() {
        return new PDO('mysql:host=localhost;dbname=veritabani;charset=utf8', 'kullanici', 'sifre');
    }
}
```

## Kullanım

```php
require 'Orm.php';

class Kullanici extends Orm {
    protected $tablo = 'kullanicilar';
    protected $fillable = ['ad', 'soyad', 'email'];
    protected $rules = [
        'email' => ['required', 'email', 'unique'],
        'ad' => ['required', 'min:2']
    ];
}

// Yeni kullanıcı oluşturma
$kullanici = new Kullanici();
$kullanici->fill([
    'ad' => 'Tanju',
    'soyad' => 'Yıldız',
    'email' => 'tanju@example.com'
]);

if ($kullanici->validate()) {
    $kullanici->save();
} else {
    print_r($kullanici->getErrors());
}

// Veri çekme
$aktifKullanicilar = (new Kullanici())
    ->where('durum', '=', 1)
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();

// İlişkili modellerle eager loading
$kullanicilar = (new Kullanici())
    ->with('roller')
    ->get();

// Sayfalama
$sayfa = 2;
$sonuc = (new Kullanici())->paginate($sayfa, 15);
print_r($sonuc['data']);
echo "Toplam sayfa: " . $sonuc['sayfa_sayisi'];
```

## Debug Modu

```php
$orm = new Kullanici();
$orm->enableDebug(true);
$orm->where('durum', 1)->get();
print_r($orm->getQueryLog());
echo "Son sorgu: " . $orm->getLastQuery();
```

### Lisans

Bu proje MIT lisansı altında dağıtılmaktadır. Detaylar için LICENSE dosyasına bakabilirsiniz.