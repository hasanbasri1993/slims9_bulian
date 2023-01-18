<?php

use SLiMS\DB;
use SLiMS\DBMain;


// key to authenticate
const INDEX_AUTH = '1';

// main system configuration
require './sysconfig.inc.php';


error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_INDEX);

$sql = "
SELECT
    master_santri.id AS 'santri_id',
    master_santri.induk AS 'member_id',
    CONCAT(master_santri.nama) AS 'member_name',
    CONCAT(master_kelas.kelas,'',master_rombel.rombel,'-',master_sekolah.sekolah) AS 'member_notes',
    IF (master_santri.jk='L','1','0') AS 'gender',
    CONCAT(master_santri.alamat_orangtua,' ',master_santri.desa,' ',kabupaten,' ',master_santri.kodepos,' ',master_santri.propinsi) AS 'member_address',
    master_santri.tanggal_lahir AS 'birth_date',
    master_santri.kodepos AS 'postal_code',
    master_santri.dulidomail AS 'member_mail_address',
    master_santri.dulidomail AS 'member_email',
    master_santri.foto AS 'foto',
    IF (foto IS NULL OR foto='','photo.png',CONCAT('member_',master_santri.induk,'.JPG')) AS 'member_image'
FROM master_rombel_siswa
    LEFT JOIN master_rombel ON master_rombel.id=master_rombel_siswa.id_rombel
    LEFT JOIN master_ajaran ON master_ajaran.id=master_rombel.tahun_ajaran
    LEFT JOIN master_kelas ON master_kelas.id=master_rombel.id_kelas
    LEFT JOIN master_sekolah ON master_sekolah.id=master_kelas.id_sekolah
    LEFT JOIN master_santri ON master_santri.id=master_rombel_siswa.id_santri
WHERE
    master_ajaran.STATUS='Y' AND
    (master_sekolah.sekolah='TMI')
ORDER BY
    master_kelas.id ASC,
    master_rombel.id ASC,
    master_santri.nama ASC";
$result = DBMain::getInstance()->query($sql);

$prepareInsert = DB::getInstance()->prepare(
    "INSERT INTO member (
					member_id,
					member_name,
					member_notes,
					gender,
					member_address,
					birth_date,
					postal_code,
					member_mail_address,
					member_email,
					member_image,
				    mpasswd,
					expire_date,
                    member_type_id,
                    member_since_date,
                    register_date,
                    last_update
				) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$prepareUpdate = DB::getInstance()->prepare(
    "UPDATE member SET
					member_name = ?,
					member_notes = ?,
					gender = ?,
					member_address = ?,
					birth_date = ?,
					postal_code = ?,
					member_mail_address = ?,
					member_email = ?,
					member_image = ?,
                    member_type_id = ?,
                    last_update = ?
				WHERE member_id = ?
				"
);


if ($result->rowCount() > 0) {
    $updated = 0;
    $inserted = 0;

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $member_id = $row["member_id"];
        $member_name = $row["member_name"];
        $member_notes = $row["member_notes"];
        $gender = $row["gender"];
        $member_address = $row["member_address"];
        $birth_date = $row["birth_date"] == "0000-00-00" ? "1997-11-11" : $row["birth_date"];
        $postal_code = $row["postal_code"];
        $member_mail_address = $row["member_mail_address"];
        $member_email = $row["member_email"];
        $member_image = $row["member_image"];
        $mpassword = '$2y$10$CZ0AT5eeoDCa4jQGIo2kaOTy.H8Zg.oSfAXjm2Ed9YvipcREg6IjW';//1234
        $exp = '2023-05-29';
        $member_since_date = date("Y-m-d");
        $member_type_id = 1;

        $prepareSelectSantri = DB::getInstance()->query("SELECT member_id FROM member WHERE member_id = $member_id");
        echo '<pre>' . $member_id;
        print_r($row);
        if ($prepareSelectSantri->rowCount() > 0) {
            $prepareUpdate->execute(
                [
                    $member_name,
                    $member_notes,
                    $gender,
                    $member_address,
                    $birth_date,
                    $postal_code,
                    $member_mail_address,
                    $member_email,
                    $member_image,
                    $member_type_id,
                    date("Y-m-d"),
                    $member_id
                ]
            );
            $updated++;
        } else {
            $prepareInsert->execute(
                [
                    $member_name,
                    $member_notes,
                    $gender,
                    $member_address,
                    $birth_date,
                    $postal_code,
                    $member_mail_address,
                    $member_email,
                    $member_image,
                    $mpassword,
                    $exp,
                    $member_type_id,
                    $member_since_date,
                    $member_since_date,
                    $member_since_date
                ]
            );
            $inserted++;
        }
        echo '</pre>';
        copy(
            "https://res.cloudinary.com/dqq8siyfu/image/upload/w_500,h_500,c_thumb,g_face,q_auto:good/fotosantriaws/$row[santri_id]/$row[foto]",
            "images/persons/member_$member_id.JPG"
        );
    }
    echo "Success updated = $updated and inserted $inserted <br>";
    echo "<a href='/'>Back To Main</a>";
} else {
    echo "0 results";
}
