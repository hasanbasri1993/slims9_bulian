<?php



error_reporting(E_ALL);
ini_set('display_errors', '1');
//mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_INDEX);

$profile = require_once './config/database.php';

// Create connection
extract($profile['nodes']["PesantrenCyber"]);
$conn = new mysqli($host, $username, $password, $database, $port);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create connection
extract($profile['nodes']["SLiMS"]);
$conn_localhost = new mysqli($host, $username, $password, $database, $port);
// Check connection
if ($conn_localhost->connect_error) {
    die("Connection failed: " . $conn_localhost->connect_error);
}

$sql = "
SELECT
    master_santri.induk AS 'member_id',
    IF (foto IS NULL OR foto='','v1601997443/fotosantriaws/person-icon_v4pkh1_kysvrv.jpg',CONCAT('fotosantriaws/',master_santri.id,'/',foto)) AS 'member_image'
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
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $updated = 0;
    $inserted = 0;

    while ($row = $result->fetch_assoc()) {
        $member_id = $row["member_id"];
        $member_image = $row["member_image"];
        var_dump($member_id);
        if (!is_null($member_id)){
            $prepareSelectSantri = $conn_localhost->query("SELECT member_id FROM member WHERE member_id = $member_id");
            echo  $member_id;
            $photoMember = $member_id.".JPG";
            if ( copy("https://res.cloudinary.com/dqq8siyfu/image/upload/h_2048,q_auto:good,e_auto_brightness/".$member_image, "images/persons/member_".$photoMember) ) {
                echo "Copy success! $member_image";
            }else{
                echo "Copy failed. $member_id";
            }
        }
    }
    echo "Success updated = $updated and inserted $inserted <br>";
    echo "<a href='/'>Back To Main</a>";
} else {
    echo "0 results";
}
$conn_localhost->close();
$conn->close();
