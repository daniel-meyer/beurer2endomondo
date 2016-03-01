<?php

date_default_timezone_set ( 'Europe/Warsaw' );

function mysystem($command)
{
    if (!($p = popen("($command)2>&1", "r"))) {
        return 126;
    }
    $out = [];
    while (!feof($p)) {
        $out[] = trim(fgets($p, 1000));
    }
    pclose($p);
    return $out;
}

function parseCSV($csvData, $stringCols = [])
{
    $headers = str_getcsv(array_shift($csvData), ',');
    $data = [];
    foreach($csvData as $i => $rowData) {
        $row = str_getcsv($rowData, ',');
        foreach ($row as $k => $v) {
            if (!empty($headers[$k])) {
                $key = $headers[$k];
                $data[$i][$key] = in_array($key, $stringCols) ? $v : floatval($v);
            }
        }
    }
    return $data;
}

if (!isset($argv[1])) {
    die('Usage: php beurer2endomondo.php  <beurerExportFile> [<startAltitude>]');
}

//var_dump($argv);

//$tableNames = mysystem('mdb-tables -1 ' . $argv[1]);
//var_dump($tableNames);

$trainingUhr = current(parseCSV(mysystem('mdb-export -D \'%s\' ' . $argv[1] . ' TrainingUhr'), ['Kommentar']));

$date = DateTime::createFromFormat('U', $trainingUhr['Datum']);
$date->setTimezone(new DateTimeZone('Europe/Warsaw'));
//var_dump($trainingUhr);

echo 'Preparing activity: ' . $trainingUhr['Kommentar'] . "\n";

//
$uhrHR = parseCSV(mysystem('mdb-export ' . $argv[1] . ' Uhr_HR'));
//var_dump($uhrHR);
//
$uhrSpeed = parseCSV(mysystem('mdb-export ' . $argv[1] . ' Uhr_Speed'));
//var_dump($uhrSpeed);
//
$uhrHoehe = parseCSV(mysystem('mdb-export ' . $argv[1] . ' Uhr_Hoehe'));
//var_dump($uhrHoehe);

$distance = 0;
$totalDistance = $trainingUhr['Strecke'] * 1000;

$startAltitude = isset($argv[2]) ? floatval($argv[2]) : $uhrHoehe[0]['Hoehe'];
$altitudeOffset = $startAltitude - $uhrHoehe[0]['Hoehe'];
echo 'Altitude offset: ' . $altitudeOffset . "\n";
ob_start();
echo '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>';
?>
<TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2"
                        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                        xsi:schemaLocation="http://www.garmin.com/xmlschemas/ActivityExtension/v1 http://www.garmin.com/xmlschemas/ActivityExtensionv1.xsd http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2 http://www.garmin.com/xmlschemas/TrainingCenterDatabasev2.xsd">

    <Activities>
        <Activity Sport="Running">
            <Name><?= $trainingUhr['Kommentar'] ?></Name>
            <Notes><?= $trainingUhr['Kommentar'] ?></Notes>
            <Id><?= $date->format('c') ?></Id>
            <Lap StartTime="<?= $date->format('c') ?>">
                <TotalTimeSeconds><?= $trainingUhr['Dauer'] ?></TotalTimeSeconds>
                <DistanceMeters><?= $totalDistance ?></DistanceMeters>
                <MaximumSpeed><?= $trainingUhr['Geschwindigkeit_Max'] ?></MaximumSpeed>
                <Calories><?= $trainingUhr['Kcal'] ?></Calories>
                <AverageHeartRateBpm xsi:type="HeartRateInBeatsPerMinute_t">
                    <Value><?= $trainingUhr['HF_Durchschnitt'] ?></Value>
                </AverageHeartRateBpm>
                <MaximumHeartRateBpm xsi:type="HeartRateInBeatsPerMinute_t">
                    <Value><?= $trainingUhr['HF_Max'] ?></Value>
                </MaximumHeartRateBpm>
                <Intensity>Active</Intensity>
                <TriggerMethod>Manual</TriggerMethod>

<?php foreach ($uhrHR as $i => $rowHR):
    if (!array_key_exists('HR', $rowHR) || !isset($uhrHoehe[$i]['Hoehe']) || !isset($uhrSpeed[$i]['Speed']) ) continue;
    $rowSpeed = $uhrSpeed[$i]; $rowAlitude = $uhrHoehe[$i];
    $distance += $rowSpeed['Speed'] * 1000 / 60;
    ?>
                <Trackpoint>
                    <Time><?= $date->format('c') ?></Time>
                    <DistanceMeters><?= $distance > $totalDistance ? $totalDistance : $distance ?></DistanceMeters>
                    <HeartRateBpm xsi:type="HeartRateInBeatsPerMinute_t">
                        <Value><?= $rowHR['HR'] ?></Value>
                    </HeartRateBpm>
                    <AltitudeMeters><?= ($rowAlitude['Hoehe'] + $altitudeOffset) ?></AltitudeMeters>
                    <SensorState>Present</SensorState>
                    <Extensions>
                        <ActivityTrackpointExtension
                            xmlns="http://www.garmin.com/xmlschemas/ActivityExtension/v1" SourceSensor="Footpod">
                            <Speed><?= $rowSpeed['Speed'] ?></Speed>
                        </ActivityTrackpointExtension>
                    </Extensions>
                </Trackpoint>
<?php $date->modify('+1 minute'); endforeach; ?>

                <Extensions>
                    <ActivityLapExtension xmlns="http://www.garmin.com/xmlschemas/ActivityExtension/v1">
                        <AvgSpeed><?= $trainingUhr['Geschwindigkeit_Durchschnitt'] ?></AvgSpeed>
                    </ActivityLapExtension>
                </Extensions>
            </Lap>
            <Creator xsi:type="Device_t">
                <Name>PM 90</Name>
                <UnitId>371</UnitId>
                <ProductID>782</ProductID>
                <Version>
                    <VersionMajor>0</VersionMajor>
                    <VersionMinor>0</VersionMinor>
                    <BuildMajor>0</BuildMajor>
                    <BuildMinor>0</BuildMinor>
                </Version>
            </Creator>
        </Activity>
    </Activities>

    <Author xsi:type="Application_t">
        <Name>Beurer Gmbh</Name>
        <Build>
            <Version>
                <VersionMajor>2</VersionMajor>
                <VersionMinor>0</VersionMinor>
                <BuildMajor>0</BuildMajor>
                <BuildMinor>14</BuildMinor>
            </Version>
            <Type>Internal</Type>
            <Time>Feb 22 2016, 21:19:54</Time>
            <Builder>meyer</Builder>
        </Build>
        <LangID>EN</LangID>
        <PartNumber>006-A0XXX-00</PartNumber>
    </Author>

</TrainingCenterDatabase>

<?php
$outputFilename = pathinfo($argv[1], PATHINFO_FILENAME) . '.tcx';
file_put_contents($outputFilename, ob_get_contents());
ob_end_clean();
echo 'Distance[m]: ' . $distance . ' vs ' . ($trainingUhr['Strecke'] * 1000) .  "\n";
echo 'Converting success. Result has been save to file: ' . $outputFilename;
