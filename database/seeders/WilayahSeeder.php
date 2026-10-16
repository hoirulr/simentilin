<?php

namespace Database\Seeders;

use App\Models\Desa;
use App\Models\Kecamatan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WilayahSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Data 8 Kecamatan di Kabupaten Bangka beserta Desa/Kelurahan.
     * Kode menggunakan format BPS: 19.01.XX.YYYY
     *
     * @var array<string, array{code: string, desas: array<int, array{name: string, code: string}>}>
     */
    private array $wilayahData = [
        'Sungailiat' => [
            'code' => '19.01.01',
            'desas' => [
                ['name' => 'Sungailiat', 'code' => '19.01.01.1001'],
                ['name' => 'Kudai', 'code' => '19.01.01.2001'],
                ['name' => 'Kenanga', 'code' => '19.01.01.2002'],
                ['name' => 'Sinar Baru', 'code' => '19.01.01.2003'],
                ['name' => 'Sri Menanti', 'code' => '19.01.01.2004'],
                ['name' => 'Surya Timur', 'code' => '19.01.01.2005'],
                ['name' => 'Jelitik', 'code' => '19.01.01.2006'],
                ['name' => 'Matras', 'code' => '19.01.01.2007'],
            ],
        ],
        'Merawang' => [
            'code' => '19.01.02',
            'desas' => [
                ['name' => 'Merawang', 'code' => '19.01.02.2001'],
                ['name' => 'Kimak', 'code' => '19.01.02.2002'],
                ['name' => 'Baturusa', 'code' => '19.01.02.2003'],
                ['name' => 'Balunijuk', 'code' => '19.01.02.2004'],
                ['name' => 'Riding Panjang', 'code' => '19.01.02.2005'],
                ['name' => 'Jada Bahrin', 'code' => '19.01.02.2006'],
                ['name' => 'Pagarawan', 'code' => '19.01.02.2007'],
                ['name' => 'Dwi Makmur', 'code' => '19.01.02.2008'],
            ],
        ],
        'Pemali' => [
            'code' => '19.01.03',
            'desas' => [
                ['name' => 'Pemali', 'code' => '19.01.03.2001'],
                ['name' => 'Penyamun', 'code' => '19.01.03.2002'],
                ['name' => 'Air Duren', 'code' => '19.01.03.2003'],
                ['name' => 'Karya Makmur', 'code' => '19.01.03.2004'],
                ['name' => 'Sempan', 'code' => '19.01.03.2005'],
                ['name' => 'Seri Kembang', 'code' => '19.01.03.2006'],
            ],
        ],
        'Mendo Barat' => [
            'code' => '19.01.04',
            'desas' => [
                ['name' => 'Kota Kapur', 'code' => '19.01.04.2001'],
                ['name' => 'Kemuja', 'code' => '19.01.04.2002'],
                ['name' => 'Petaling', 'code' => '19.01.04.2003'],
                ['name' => 'Air Buluh', 'code' => '19.01.04.2004'],
                ['name' => 'Air Menduk', 'code' => '19.01.04.2005'],
                ['name' => 'Paya Benua', 'code' => '19.01.04.2006'],
                ['name' => 'Cengkong Abang', 'code' => '19.01.04.2007'],
                ['name' => 'Zed', 'code' => '19.01.04.2008'],
            ],
        ],
        'Belinyu' => [
            'code' => '19.01.05',
            'desas' => [
                ['name' => 'Belinyu', 'code' => '19.01.05.2001'],
                ['name' => 'Gunung Muda', 'code' => '19.01.05.2002'],
                ['name' => 'Air Jukung', 'code' => '19.01.05.2003'],
                ['name' => 'Riding Panjang', 'code' => '19.01.05.2004'],
                ['name' => 'Bukit Ketok', 'code' => '19.01.05.2005'],
                ['name' => 'Lumut', 'code' => '19.01.05.2006'],
                ['name' => 'Kuto Panji', 'code' => '19.01.05.2007'],
            ],
        ],
        'Riau Silip' => [
            'code' => '19.01.06',
            'desas' => [
                ['name' => 'Riau', 'code' => '19.01.06.2001'],
                ['name' => 'Silip', 'code' => '19.01.06.2002'],
                ['name' => 'Deniang', 'code' => '19.01.06.2003'],
                ['name' => 'Berbura', 'code' => '19.01.06.2004'],
                ['name' => 'Pugul', 'code' => '19.01.06.2005'],
                ['name' => 'Pangkal Niur', 'code' => '19.01.06.2006'],
            ],
        ],
        'Bakam' => [
            'code' => '19.01.07',
            'desas' => [
                ['name' => 'Bakam', 'code' => '19.01.07.2001'],
                ['name' => 'Dalil', 'code' => '19.01.07.2002'],
                ['name' => 'Kapuk', 'code' => '19.01.07.2003'],
                ['name' => 'Mabat', 'code' => '19.01.07.2004'],
                ['name' => 'Mangka', 'code' => '19.01.07.2005'],
                ['name' => 'Neknang', 'code' => '19.01.07.2006'],
            ],
        ],
        'Puding Besar' => [
            'code' => '19.01.08',
            'desas' => [
                ['name' => 'Puding Besar', 'code' => '19.01.08.2001'],
                ['name' => 'Nibung', 'code' => '19.01.08.2002'],
                ['name' => 'Saing', 'code' => '19.01.08.2003'],
                ['name' => 'Sempan', 'code' => '19.01.08.2004'],
                ['name' => 'Labuh Air Pandan', 'code' => '19.01.08.2005'],
                ['name' => 'Tanah Bawah', 'code' => '19.01.08.2006'],
            ],
        ],
    ];

    /**
     * Seed the wilayah (kecamatan & desa) data for Kabupaten Bangka.
     */
    public function run(): void
    {
        foreach ($this->wilayahData as $kecamatanName => $data) {
            $kecamatan = Kecamatan::create([
                'name' => $kecamatanName,
                'code' => $data['code'],
            ]);

            foreach ($data['desas'] as $desaData) {
                Desa::create([
                    'kecamatan_id' => $kecamatan->id,
                    'name' => $desaData['name'],
                    'code' => $desaData['code'],
                ]);
            }
        }
    }
}
