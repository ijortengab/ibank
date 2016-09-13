Anomali BNI
===========

Terjadi anomali pada hasil request mutasi rekening dengan kasus sebagai berikut:

 - Request pada tanggal 30 Jan 2016 pukul 00:24

 - Pilih mutasi rekening dengan kriteria pencarian "Jangka Waktu Transaksi",
   contoh:
   Tanggal Awal: 03-Jan-2016
   Tanggal Akhir: 30-Jan-2016

Maka akan muncul error

 > Tanggal Akhir tidak boleh melebihi tanggal hari ini

Padahal tanggal akhir sama dengan hari ini, kemungkinan disebabkan pukul 00:00
sd 01:00 masih belum dianggap hari baru oleh server.
