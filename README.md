IBank, PHP Internet Banking Client
==================================

IBank mengubah cara anda mendapatkan informasi saldo dan mutasi rekening
dari mengunjungi website via browser, klik sana, klik sini, input sana,
input sini, menjadi satu kali eksekusi PHP Script.

## Status Saat Ini

**Unstable**

## Disclaimer

Todo

## Instalasi

Instalasi IBank sebagai project mandiri:

```sh
git clone https://github.com/ijortengab/ibank.git
cd ibank
composer install -vvv
```

Instalasi IBank bagian dari project PHP Anda:

```
cd ~/path/to/your/root/php/project
composer require ijortengab/ibank -vvv
```

Perhatian:

> Gunakan selalu argument berisik `-vvv` 
> karena website `packagist.org` terlalu lamban.

## Cara Penggunaan

Todo

## BNI

Fitur yang tersedia untuk bank BNI adalah:
 - dapat saldo terakhir (string).
 - dapat mutasi rekening (array).

Internet Banking BNI sudah dicoba dengan kriteria sebagai berikut:
 - tipe rekening (account) yakni Tabungan/Giro
 - satu username hanya terdapat satu rekening (account).
Selain kriteria diatas, maka akan gagal karena belum diketahui.


## BCA

Todo

## Bank Mandiri

Todo

## BRI

Todo

## Bank Muamalat

Todo

## Bank Syariah Mandiri

Todo


## Variable $information

- $information['username']  
  Username untuk login ke internet banking, digunakan pada seluruh bank.  
- $information['password']  
  password untuk login ke internet banking, digunakan pada seluruh bank.  
  


