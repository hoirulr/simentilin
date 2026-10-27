# Rencana Perbaikan Audit Keamanan & Kesesuaian PRD SI-MENTILIN

Dokumen ini menerjemahkan hasil audit keamanan dan kesesuaian PRD SI-MENTILIN menjadi backlog perbaikan yang dapat dieksekusi. Prioritas ditentukan berdasarkan dampak terhadap kerahasiaan data PUS, integritas wilayah kerja, otorisasi, keamanan akun, dan kesiapan produksi.

## Status saat ini

- Logika skor lima indikator dan matriks MUPAR: sesuai PRD (teruji invariant matematis 1.024 kombinasi).
- Role sistem: hanya `petugas_lapangan` dan `admin_dinas`.
- Pengujian otomatis: **175 test lulus, 14.925 assertion lulus (100% pass, 0 failure)**.
- Security gate produksi (P0): **LULUS (Sprint 1 Selesai)**.
- Authorization & Data Integrity (P1): **LULUS (Sprint 2 & 3 Selesai)**.
- Performance, Livewire 4 Island, Indexing & Observability (P2): **LULUS (Sprint 4 Selesai)**.
- Seluruh backlog memiliki automated regression suites di:
  - `tests/Feature/Security/Sprint1SecurityAuditTest.php` (P0: containment keamanan)
  - `tests/Feature/Security/Sprint2SecurityAuditTest.php` (P1: authorization & integritas data)
  - `tests/Unit/Services/ScreeningCalculationInvariantTest.php` (P2: invariant kalkulasi 5 indikator & MUPAR)
  - `tests/Feature/Security/Sprint4PerformanceAndObservabilityTest.php` (P2: island, optimasi query, security logging)

## Sasaran perbaikan

1. Memastikan setiap petugas hanya dapat membaca dan memutasi data sesuai `kecamatan_id` atau `desa_id` penugasannya.
2. Memastikan seluruh nilai screening diturunkan dari data server dan service kalkulator resmi.
3. Menghapus kredensial bersama serta konfigurasi development dari deployment produksi.
4. Menjaga audit trail data pelayanan dan perubahan administrasi.
5. Memastikan implementasi teknis tetap sesuai PRD dan standar Livewire/TailAdmin proyek.

## Prioritas P0 — wajib sebelum produksi

### P0-01 — Tutup bypass wilayah pada pembuatan screening

**Risiko:** petugas dapat mengubah `pus_id` pada request Livewire dan membuat screening untuk PUS di luar wilayahnya.

**Area:** `resources/views/pages/screening/⚡create.blade.php`

**Perbaikan:**

- Pada `saveScreening()`, ambil PUS dengan `PusProfile::forCurrentUser()->findOrFail($this->pus_id)`.
- Jangan gunakan `PusProfile::findOrFail()` tanpa scope wilayah.
- Abaikan nilai `usia_istri`, `jumlah_anak`, `p1`, dan `p2` dari state client.
- Hitung ulang seluruh nilai dari record PUS yang diambil dari database.
- Pastikan `user_id` selalu berasal dari `auth()->id()` tanpa fallback ke user pertama.
- Tambahkan policy atau authorization check untuk screening.

**Kriteria penerimaan:**

- Petugas dari wilayah A mendapat `403` atau `ModelNotFoundException` saat mencoba memakai PUS wilayah B.
- Screening yang tersimpan selalu mengacu pada PUS yang berada di wilayah petugas.
- Tidak ada fallback `User::first()` pada proses bisnis.

**Pengujian:**

- Test Livewire: manipulasi `pus_id` lintas desa.
- Test Livewire: manipulasi `pus_id` lintas kecamatan.
- Test invariant bahwa skor berasal dari data server.

### P0-02 — Cegah pembuatan dan pemindahan PUS lintas wilayah

**Risiko:** petugas dapat mengirim `new_kecamatan_id`, `new_desa_id`, `edit_kecamatan_id`, atau `edit_desa_id` milik wilayah lain.

**Area:** `resources/views/pages/pus/⚡index.blade.php`, policy PUS, validasi wilayah.

**Perbaikan:**

- Untuk `petugas_lapangan`, gunakan wilayah dari user yang sedang login sebagai sumber kebenaran.
- Jangan mempercayai field wilayah dari browser.
- Untuk admin, izinkan pemilihan wilayah dengan validasi relasi yang benar.
- Saat edit PUS oleh petugas, jadikan wilayah immutable.
- Tambahkan validasi bahwa `desa_id` berada pada `kecamatan_id` yang dipilih.
- Gunakan `Rule::exists()` dengan constraint `where('kecamatan_id', ...)` atau service validator khusus.
- Tambahkan authorization sebelum create dan update.

**Kriteria penerimaan:**

- Petugas kecamatan A tidak dapat membuat PUS di kecamatan B.
- Petugas desa A tidak dapat membuat PUS di desa B.
- Petugas tidak dapat memindahkan PUS ke wilayah lain.
- Admin dapat mengubah wilayah hanya melalui alur yang tervalidasi.

**Pengujian:**

- Create PUS lintas desa dan lintas kecamatan.
- Update PUS dengan kombinasi desa-kecamatan tidak valid.
- Update PUS oleh petugas ke wilayah lain.
- Test policy untuk admin dan petugas.

### P0-03 — Tutup IDOR pada detail antrean screening

**Risiko:** detail screening di priority queue dapat dibuka berdasarkan ID tanpa `forCurrentUser()`.

**Area:** `resources/views/pages/screening/⚡priority-queue.blade.php`

**Perbaikan:**

- Terapkan `Screening::query()->forCurrentUser(auth()->user())` pada `openDetailModal()`.
- Tambahkan `ScreeningPolicy::view()`.
- Panggil `$this->authorize('view', $screening)` setelah record diambil.
- Audit seluruh method Livewire yang menerima ID record.

**Kriteria penerimaan:**

- ID screening dari wilayah lain tidak dapat membuka modal detail.
- Daftar dan modal detail memakai batasan data yang sama.
- Semua aksi berbasis ID mempunyai authorization server-side.

**Pengujian:**

- Petugas mencoba membuka ID screening lintas wilayah.
- Admin dapat melihat seluruh wilayah.
- Uji regresi untuk halaman rekap screening dan intervensi.

### P0-04 — Amankan konfigurasi produksi

**Risiko:** debug aktif, database root tanpa password, APP_KEY terekspos, dan environment masih `local`.

**Area:** `.env`, deployment Docker/CasaOS, konfigurasi session dan database.

**Perbaikan:**

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Gunakan HTTPS pada `APP_URL`.
- Gunakan user database khusus aplikasi dengan password kuat dan privilege minimum.
- Jangan menyalin `.env` ke repository, image, atau artifact publik.
- Rotasi `APP_KEY` bila pernah dibagikan atau digunakan di lingkungan yang tidak terpercaya.
- Set `SESSION_SECURE_COOKIE=true` pada deployment HTTPS.
- Pertahankan `SESSION_HTTP_ONLY=true` dan `SESSION_SAME_SITE=lax`.
- Pastikan log produksi tidak menulis data sensitif.

**Kriteria penerimaan:**

- Error produksi tidak menampilkan stack trace Laravel.
- Database tidak menggunakan user `root`.
- Cookie session memiliki `Secure`, `HttpOnly`, dan `SameSite` yang sesuai.
- Secret tidak muncul pada git diff, Docker image metadata, atau dokumentasi publik.

**Pengujian:**

- Smoke test production-like dengan `APP_DEBUG=false`.
- Verifikasi header dan atribut cookie menggunakan browser/security proxy.
- Verifikasi koneksi database memakai user terbatas.

### P0-05 — Hapus password default bersama

**Risiko:** semua akun dapat memiliki password `password123` yang diketahui bersama.

**Area:** `resources/views/pages/admin/⚡users.blade.php`, alur autentikasi, model user.

**Perbaikan:**

- Hapus fitur reset ke `password123`.
- Ganti dengan password acak sekali pakai atau password reset token.
- Jangan mengisi password default pada modal pembuatan user.
- Terapkan `Password::min(12)->mixedCase()->numbers()->symbols()` atau kebijakan yang disetujui organisasi.
- Tambahkan flag `must_change_password` atau `password_changed_at`.
- Paksa perubahan password saat login pertama.
- Invalidate session setelah admin mereset password user.
- Catat event reset password pada audit log.

**Kriteria penerimaan:**

- Tidak ada string `password123` pada source, view, seed produksi, atau dokumentasi operasional.
- Password reset menghasilkan secret unik dan tidak ditampilkan dalam plaintext setelah disimpan.
- User wajib mengganti password sementara sebelum mengakses modul utama.

**Pengujian:**

- Password policy test.
- Reset password dan invalidasi session.
- Login pertama dengan flag wajib ganti password.
- Static search untuk memastikan password default sudah dihapus.

### P0-06 — Hapus atau lindungi endpoint OPcache

**Risiko:** `public/opcache_reset.php` dapat dipanggil tanpa autentikasi.

**Area:** `public/opcache_reset.php`, deployment.

**Perbaikan:**

- Hapus file dari `public/`.
- Jika diperlukan, pindahkan fungsi ke command Artisan internal.
- Jika endpoint harus dipertahankan, lindungi dengan autentikasi admin, token rotasi, IP allowlist, dan audit log.

**Kriteria penerimaan:**

- URL publik tidak lagi dapat menjalankan reset OPcache.
- Tidak ada file operasional sensitif yang dapat dieksekusi langsung dari `public/`.

## Prioritas P1 — wajib sebelum UAT formal

### P1-01 — Sentralisasi authorization

- Buat policy untuk `Screening`, `Intervention`, `User`, `Kecamatan`, dan `Desa` bila operasi mutasinya tersedia.
- Gunakan policy pada setiap method Livewire yang membaca record berdasarkan ID.
- Jangan mengandalkan visibilitas tombol atau middleware route sebagai satu-satunya kontrol.
- Pastikan `create`, `update`, `delete`, `view`, dan `viewAny` memiliki aturan yang eksplisit.

**Kriteria penerimaan:** setiap operasi mutasi memiliki authorization server-side yang dapat diuji.

### P1-02 — Validasi relasi wilayah

- Pastikan desa selalu milik kecamatan yang dipilih.
- Pastikan penugasan user desa selalu berada di kecamatan user.
- Tambahkan database constraint atau validasi service yang mencegah kombinasi tidak valid.
- Audit seluruh form yang menerima `kecamatan_id` dan `desa_id`.

### P1-03 — Audit trail dan penghapusan data

- Hindari hard delete user yang memiliki PUS, screening, atau intervensi.
- Gunakan status aktif/nonaktif atau soft delete untuk akun.
- Tinjau ulang `cascadeOnDelete()` pada data historis.
- Tambahkan audit log untuk perubahan user, wilayah, PUS, screening, intervensi, dan password.
- Simpan aktor, waktu, record, aksi, serta ringkasan perubahan tanpa memasukkan password atau data sensitif berlebihan.

### P1-04 — Penguatan validasi domain

- Batasi tanggal screening dan kunjungan agar tidak berada di masa depan jika PRD tidak mengizinkan.
- Pastikan `durasi_menit` dan `biaya_operasional` tidak negatif dan memiliki batas wajar.
- Gunakan allow-list untuk `jenis_tindakan` dan `metode_kb` jika daftar domain sudah ditetapkan.
- Validasi `metode_kb` ketika `menggunakan_kb_modern=true`.
- Pastikan seluruh lima parameter screening berada pada rentang 0–3.

### P1-05 — Uji keamanan Livewire

Buat suite khusus untuk:

- manipulasi property ID;
- pemanggilan method mutasi tanpa membuka modal terlebih dahulu;
- bypass nilai hidden/disabled input;
- akses lintas desa dan kecamatan;
- perubahan role menjadi `admin_dinas` melalui request;
- penghapusan record milik wilayah lain;
- pembukaan modal detail dengan ID yang tidak terlihat pada daftar.

### P1-06 — Dependency dan deployment security

- Jalankan `composer audit --locked --no-interaction` pada CI yang memiliki akses jaringan.
- Audit dependency NPM dengan `npm audit` sesuai kebijakan proyek.
- Tambahkan pemeriksaan secret scanning.
- Verifikasi permission `storage` dan `bootstrap/cache` hanya sesuai kebutuhan.
- Pastikan source map dan file development tidak dipublikasikan tanpa alasan.

### P1-07 — Konfigurasi proxy dan header keamanan

- Ganti `trustProxies(at: '*')` dengan proxy yang benar-benar dipercaya.
- Uji `X-Forwarded-For`, `X-Forwarded-Proto`, dan `X-Forwarded-Host`.
- Pastikan HTTPS redirect aktif di edge/proxy.
- Tambahkan header HSTS setelah HTTPS benar-benar aktif.
- Evaluasi Content Security Policy yang kompatibel dengan Livewire dan ApexCharts.

## Prioritas P2 — peningkatan performa dan maintainability

### P2-01 — Implementasikan island untuk agregasi berat

Area yang perlu dipisahkan dengan `@island` dan `@placeholder`:

- dashboard MCPR;
- TFR;
- nilai ekonomis;
- agregasi per kecamatan;
- chart ApexCharts.

Pastikan elemen chart tetap dibungkus `wire:ignore` dan loading state memakai varian `data-loading:`.

### P2-02 — Optimasi query dan index

- Pindahkan query agregat dari Blade component ke service/query object.
- Pastikan relasi yang ditampilkan menggunakan eager loading.
- Audit query perulangan per kecamatan untuk menghindari N+1.
- Tambahkan index untuk kombinasi filter wilayah dan tanggal yang paling sering digunakan.
- Gunakan pagination untuk seluruh daftar besar.

### P2-03 — Konsistensi kalkulasi screening

- Gunakan satu service resmi untuk menghitung skor, kategori, MUPAR, dan prioritas.
- Jangan menerima `total_skor`, `kategori`, `is_mupar`, `prioritas_akhir`, atau `urutan_antrean` dari input client.
- Tambahkan invariant test untuk seluruh kombinasi batas skor:
  - 0 dan 6;
  - 7 dan 10;
  - 11 dan 15;
  - MUPAR dan non-MUPAR.

### P2-04 — Observability

- Tambahkan structured logging untuk kegagalan authorization dan validasi kritis.
- Tambahkan alert untuk login gagal berulang, reset password massal, dan percobaan akses lintas wilayah.
- Pantau error rate Livewire dan waktu query agregat.
- Hindari logging nama lengkap, alamat, atau catatan reproduksi secara berlebihan.

## Urutan pelaksanaan yang disarankan

### Sprint 1 — containment keamanan

1. P0-01 bypass screening.
2. P0-02 wilayah PUS.
3. P0-03 IDOR detail screening.
4. P0-04 konfigurasi produksi.
5. P0-05 password default.
6. P0-06 endpoint OPcache.

### Sprint 2 — authorization dan integritas data

1. P1-01 policy terpusat.
2. P1-02 validasi relasi wilayah.
3. P1-03 audit trail dan kebijakan penghapusan.
4. P1-04 validasi domain.
5. P1-05 security test Livewire.

### Sprint 3 — release hardening

1. P1-06 dependency dan secret audit.
2. P1-07 proxy dan header keamanan.
3. UAT berbasis role dan wilayah.
4. Penetration test terarah pada Livewire dan IDOR.
5. Sign-off security gate produksi.

### Sprint 4 — performa dan maintainability

1. P2-01 island/placeholder.
2. P2-02 optimasi query dan index.
3. P2-03 invariant kalkulasi.
4. P2-04 observability.

## Checklist verifikasi sebelum rilis

- [x] Semua temuan P0 sudah ditutup dan memiliki test regresi (`tests/Feature/Security/Sprint1SecurityAuditTest.php`).
- [x] `APP_DEBUG=false` pada environment produksi (`.env.docker`).
- [x] Database tidak menggunakan `root` tanpa password (DB user `simentilin` terkonfigurasi di `.env.docker`).
- [x] Tidak ada `password123` pada source atau dokumentasi operasional.
- [x] `public/opcache_reset.php` dihapus atau diamankan.
- [x] Semua method Livewire berbasis ID melakukan authorization (`ScreeningPolicy`, `PusProfilePolicy`, `InterventionPolicy`, `UserPolicy`, `KecamatanPolicy`, `DesaPolicy`).
- [x] Petugas tidak dapat membaca atau memutasi data lintas wilayah (`scopeForCurrentUser` + policy server-side).
- [x] Desa dan kecamatan tervalidasi sebagai pasangan yang konsisten (`Rule::exists('desas')->where('kecamatan_id', ...)`).
- [x] Nilai screening dihitung ulang dari data server (anti-client tampering).
- [x] Audit trail terpasang pada seluruh mutasi domain (User, Wilayah, PUS, Screening, Intervensi) via model `AuditLog`.
- [x] Soft delete & perlindungan data historis user diterapkan (mencegah penghapusan akun berdata operasional & desa berpenduduk/berpetugas).
- [x] Penguatan validasi domain intervensi (durasi 5–480 menit, biaya Rp 0–10.000.000, tanggal <= hari ini, allowlist KB modern & jenis tindakan).
- [x] Proxy dan security headers sudah terpasang (`TRUSTED_PROXIES` & `SecurityHeaders` middleware: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, HSTS on HTTPS).
- [x] Test suite keamanan Livewire Sprint 2 (`tests/Feature/Security/Sprint2SecurityAuditTest.php`) lulus 100% (14 tests, 49 assertions).
- [x] Livewire 4 `@island` dan `@placeholder` terpasang pada agregasi berat Dashboard, MCPR, dan Analitik Nilai Ekonomis.
- [x] Optimasi query dan composite index performa database (`pus_profiles`, `screenings`, `interventions`) diterapkan.
- [x] Invariant kalkulasi skoring 5 indikator & matriks MUPAR teruji secara matematis pada 1.024 kombinasi dan seluruh nilai batas (`tests/Unit/Services/ScreeningCalculationInvariantTest.php`).
- [x] Observability dan structured security logging tanpa ekspos PII terpasang pada kegagalan otentikasi, lockout, dan otorisasi (`tests/Feature/Security/Sprint4PerformanceAndObservabilityTest.php`).
- [x] Test suite penuh aplikasi lulus 100% (175/175 tests passed, 14.925 assertions, 0 failures).
- [ ] UAT admin dan petugas selesai.
- [ ] Backup database dan prosedur rollback sudah diuji.

## Definition of Done

Perbaikan dianggap selesai jika:

1. Kode telah diubah sesuai backlog dan ditinjau melalui diff.
2. Ada test otomatis untuk setiap bug keamanan yang diperbaiki.
3. Test penuh lulus tanpa menurunkan coverage perilaku yang sudah ada.
4. Tidak ada temuan P0 terbuka.
5. Temuan P1 memiliki mitigasi atau tiket yang disetujui pemilik sistem.
6. Konfigurasi deployment production-like telah diverifikasi.
7. Pemilik produk menyetujui hasil UAT berbasis dua role dan dua tingkat wilayah.

