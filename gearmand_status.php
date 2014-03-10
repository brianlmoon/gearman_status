<?php

$servers = array(
    "localhost:4730"
);

/*****************************************************************************
 * END CONFIG                                                                *
 *****************************************************************************/

if(!class_exists("Net_Gearman_Manager")){
    require "Net/Gearman/Manager.php";
}

$total_job_status = array();
$workers = array();

$ip_workers = array();

$server_job_status = array();

foreach($servers as $server) {

    if(($pos = strpos($server, ":")) !== false){
        $server_name = substr($server, 0, $pos);
    } else {
        $server_name = $server;
    }

    $server_job_status[$server_name] = array();

    $mgr = new Net_Gearman_Manager($server);

    $server_status = $mgr->status();

    foreach($server_status as $name => $s){

        if(empty($total_job_status[$name])){

            $server_job_status[$server_name][$name] = $s;

            // clear capable workers, we'll fill the totals in later
            $s["capable_workers"] = 0;
            $total_job_status[$name] = $s;

        } else {

            foreach($s as $k=>$v){

                $server_job_status[$server_name][$name][$k] += $v;

                // clear capable workers, we'll fill the totals in later
                if ($k == "capable_workers") {
                    $v = 0;
                }
                $total_job_status[$name][$k] += $v;

            }
        }

    }

    $server_workers = $mgr->workers();

    foreach ($server_workers as $w) {

        // the manager appears as a worker with no abilities
        if(empty($w["abilities"])) continue;

        $ip_workers[$w["ip"]][$w["id"]] = $w;

        if(empty($workers[$w["ip"]])){
            $workers[$w["ip"]] = array(
                "servers" => array(),
                "abilities" => array()
            );
        }
        $workers[$w["ip"]]["servers"][$server_name] = $server_name;

    }

}

foreach($ip_workers as $ip => $proc) {

    foreach($proc as $id=>$w) {

        foreach($w["abilities"] as $a){
            
            if (isset($workers[$w["ip"]]["abilities"][$a])) {
                $workers[$w["ip"]]["abilities"][$a]++;
            } else {
                $workers[$w["ip"]]["abilities"][$a] = 1;
            }
            
            $total_job_status[$a]["capable_workers"]++;

        }
    }
}

// add totals to per server job status
foreach ($total_job_status as $name => $j) {
    $server_job_status['Totals'][$name] = $j;
}

ksort($total_job_status);
ksort($workers);
uksort($workers, create_function(
    '$a,$b',
    'return ip2long($a) > ip2long($b);'
));


?>

<!doctype html>
<html>
    <head>
        <title>Gearman Status</title>
        <style>

        body {
            font-family: Geneva, Tahoma, Verdana;
            font-size: 16px;
        }

        h1 {
            font-size: 20px;
            margin: 0px;
        }

        h2 {
            font-size: 16px;
            margin-bottom: 6px;
        }

        table {
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        td {
            border: 1px solid black;
        }

        td.number {
            text-align: right;
        }

        td.total {
            font-weight: bold;
        }

        .worker {
            float: left;
            margin: 0 20px 20px 0;
            padding: 10px;
            border: 1px solid black;
        }

        .worker table {
            width: 100%;
        }

        </style>

    </head>
    <body>

        <h1>Gearman Status</h1>

        <h2>Job Status</h2>

        <table border=1 cellpadding=3>
            <tr>
                <th rowspan="2">Job Name</th>
                <?php foreach(array_keys($server_job_status) as $name) { ?>
                    <th colspan="3"><?=$name?></th>
                <?php } ?>
            </tr>
            <tr>
                <?php for ($i = 0; $i < count($server_job_status); $i++) { ?>
                    <th>Queue</th>
                    <th>Running</th>
                    <th>Capable</th>
                <?php } ?>
            </tr>

            <?php foreach(array_keys($total_job_status) as $name) { ?>
                <tr>
                    <td><?=$name?></td>
                    <?php foreach($server_job_status as $server => $status) { ?>
                        <?php
                            if ($server == "Totals") {
                                $total_class = "total";
                            } else {
                                $total_class = "";
                            }
                        ?>
                        <td class="number <?=$total_class?>"><?= $status[$name]['in_queue'] ?></td>
                        <td class="number <?=$total_class?>"><?= $status[$name]['jobs_running'] ?></td>
                        <td class="number <?=$total_class?>"><?= $status[$name]['capable_workers'] ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </table>

        <h2>Workers</h2>

        <?php
            $x=0;
        ?>

        <?php foreach($workers as $ip=>$w) { ?>

            <?php

            $x++;

            if($x==4){
                $x = 0;
                $class = "lastUnit";
            } else {
                $class = "";
            }

            ?>

            <div class="worker">
                <h3><?=$ip?></h3>

                Connected to:
                <ul>
                    <?php foreach($w["servers"] as $s){ ?>
                        <li><?=$s?></li>
                    <?php } ?>
                </ul>

                <table border=1 cellpadding=3>
                    <tr>
                        <th>Job</th>
                        <th>Workers</th>
                    </tr>
                    <?php ksort($w["abilities"]); ?>
                    <?php foreach($w["abilities"] as $a=>$c) { ?>
                        <tr>
                            <td><?= $a ?></td>
                            <td><?= $c ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>

        <?php } ?>

    </body>
</html>

