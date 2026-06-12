<?php

namespace Database\Seeders;

use App\Models\AbsensiPegawai;
use Illuminate\Database\Seeder;

class SetdaPegawaiUnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as [$nip, $nama, $unitKerja]) {
            AbsensiPegawai::query()
                ->where('nip', $nip)
                ->update(['unit_kerja' => $unitKerja]);
        }
    }

    private function rows(): array
    {
        return [
            ['198106102010012023', 'Esty Ratnaningtyas, S.Sos.', 'Sekretariat Daerah - Bagian Administrasi Pembangunan'],
            ['197409212006041012', 'Irianuddin, S.E.', 'Sekretariat Daerah - Bagian Administrasi Pembangunan'],
            ['198701212010011006', 'Ricky Haris Sandi, S.E.', 'Sekretariat Daerah - Bagian Administrasi Pembangunan'],
            ['197008042008012022', 'Sukmawati, S.E.', 'Sekretariat Daerah - Bagian Administrasi Pembangunan'],
            ['198206142009031008', 'dr. H. Muhammad Syaukani, M.M.', 'Sekretariat Daerah - Bagian Administrasi Pembangunan'],
            ['198704232010011002', 'Ahmad Afrian Haryandi, A.Md.', 'Sekretariat Daerah - Bagian Hukum'],
            ['198304262006041002', 'Arif Sudaryanto, S.H.', 'Sekretariat Daerah - Bagian Hukum'],
            ['198712202019032009', 'Evalia Yustina, S.H.', 'Sekretariat Daerah - Bagian Hukum'],
            ['197601032008012025', 'Isna Hastarinda Astuty, S.H.', 'Sekretariat Daerah - Bagian Hukum'],
            ['198410192010011012', 'Jefrie Fransyah, S.H., M.H.', 'Sekretariat Daerah - Bagian Hukum'],
            ['196810261994031007', 'Muhammad Taufik Rivani, S.H., M.Si.', 'Sekretariat Daerah - Bagian Hukum'],
            ['199502152022032008', 'Nurhayati, S.H.', 'Sekretariat Daerah - Bagian Hukum'],
            ['198207312006041012', 'H. Juli Khair, S.Sos.I., M.Si.', 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat'],
            ['199305052019032024', 'Maya Floria Yasmin, S.Psi., M.A.P.', 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat'],
            ['198705092010012018', 'Meliyanti, S.E.', 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat'],
            ['197501032006041012', 'Zulkifli, S.Kom.', 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat'],
            ['198008172009031007', 'Agus Wardhana, S.E., M.Ec.Dev.', 'Sekretariat Daerah - Bagian Organisasi'],
            ['198612102010011006', 'Doddy Wahyudi Enggok, S.AP.', 'Sekretariat Daerah - Bagian Organisasi'],
            ['197703202006042019', 'Dr. Eka Rahayu Normasari, S.T., M.M., M.Si.', 'Sekretariat Daerah - Bagian Organisasi'],
            ['197506262007012017', 'Elvysah Eka Yuthie, S.H., M.H.', 'Sekretariat Daerah - Bagian Organisasi'],
            ['198811142015022002', 'Na\'imatul Aufa, S.H.', 'Sekretariat Daerah - Bagian Organisasi'],
            ['198309292001122001', 'Ratih Addanenggar, S.STP. ,M.Si.', 'Sekretariat Daerah - Bagian Organisasi'],
            ['199503242017081004', 'Andi Rimba KR Mappa, S.STP.', 'Sekretariat Daerah - Bagian Pemerintahan'],
            ['196901191990101001', 'Drs. H. Diyanoor, M.A.', 'Sekretariat Daerah - Bagian Pemerintahan'],
            ['199601062018081002', 'Muhammad Faishal Muchtar, S.STP.', 'Sekretariat Daerah - Bagian Pemerintahan'],
            ['198806042007011001', 'Muhammad Farid Rivani, S.IP.', 'Sekretariat Daerah - Bagian Pemerintahan'],
            ['198703262010012006', 'Rahmatul Jannah, A.Md.', 'Sekretariat Daerah - Bagian Pemerintahan'],
            ['197604041998031009', 'Abdul Muis, S.ST., M.Eng.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198907062011011001', 'Ahmad Azmi Khairy, S.Kom.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198304202005011004', 'Ahmad Syehfi Mi\'Rajqi, S.ST.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198712302011012005', 'Andini Amalia Rifky, S.T., M.A.P., M.P.P.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['199212232016091001', 'Artaba Batuwael, S.STP.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198910302015021002', 'Dedy Setiawan, S.Kom.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['196609161986021002', 'Drs. Muhammad Ikhsan Alhak, M.Si.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198905262011011004', 'Fahrizal Syaifi, A.Md.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['197712292009012002', 'Farida Ariyani, S.T.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198002152009011002', 'Gusti Muhammad Romy Faizal, S.E.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198008302010012008', 'Hj. Ratna Fitriastuti, A.Md.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['197101041996032008', 'Hj. Zuraida, S.T.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['199209122015022001', 'Juwita Putri, S.H.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['197912042009031002', 'Mawardi, S.Kom.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['197608202005011013', 'Mohammad Rofiq, S.T., M.T.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198001092006041003', 'Muhammad Arief, S.T., M.M.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198002172006042010', 'Norhasanah, S.KM.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198612132010011003', 'Rumintang Golim, S.Kom.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['197611122010011004', 'Titok Prasetya Ananta, S.T.', 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['198208202010012020', 'Kamaria Kristina,S.Sos', 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam'],
            ['198008032005012017', 'Munawarah, S.Si., M.M.', 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam'],
            ['199208052022032007', 'Roswinda Rezeki, S.E.', 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam'],
            ['197404182000032007', 'Siane Apriliawati, S.Hut., M.M.', 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam'],
            ['198710212010011002', 'Ahmad Hamidi, S.Kom.', 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan'],
            ['198711082009032002', 'Eka Novita Christanti, S.AP.', 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan'],
            ['197802212009012001', 'Eldinar Raina Arijadi, A.Md.', 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan'],
            ['197602072010011009', 'Gusti Saufi Rizal, S.Sos, M.I.Kom.', 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan'],
            ['198711152006021001', 'Noorfahmi Arif Ridha, S.STP., M.M.', 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan'],
            ['198705022010011003', 'Ahmad Zazuli, S.M.', 'Sekretariat Daerah - Bagian Umum'],
            ['198603172011011016', 'Muhtaram, S.E., M.M.', 'Sekretariat Daerah - Bagian Umum'],
            ['198702032009031002', 'Veru Rahadian, S.AP.', 'Sekretariat Daerah - Bagian Umum'],
        ];
    }
}
