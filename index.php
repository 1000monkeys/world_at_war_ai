<?php
$host = '127.0.0.1';
$username = "root";
$password = "";
$database = 'world_at_war';
global $connection;
$connection = new mysqli($host, $username, $password, $database);

global $continents;
$continents = [
    [0, 1, 2, 3, 4, 5, 6, 7],       // 0
    [8, 9, 10, 11, 12],             // 1
    [13, 14, 15, 16, 17, 18, 19],   // 2
    [20, 21, 22, 23, 24, 25],       // 3
    [26, 27, 28, 29, 30],           // 4
    [31, 32, 33, 34, 35, 36, 37],   // 5
    [38, 39, 40, 41]                // 6
];

global $continent_bonus;
$continent_bonus = [
    4, 2, 3, 3, 2, 3, 2
];

global $targets;
$targets = [
    [1, 32],    //0
    [0, 2, 5, 6],
    [7, 4, 3, 1],
    [2, 4, 7],
    [5, 3, 2],
    [1, 4, 6, 8],   //5
    [8, 5, 1],
    [2, 3, 14],
    [6, 5, 9, 10],
    [10, 8, 5],
    [9, 12, 11],    //10
    [10, 12, 21],
    [41, 11, 10],
    [14, 16, 17, 20],
    [15, 13, 7],
    [18, 16, 14, 13],   //15
    [18, 29, 17, 15, 14, 13],
    [20, 19, 16, 13],
    [15, 16, 30, 31],
    [30, 29, 27, 26, 17],
    [26, 22, 21, 17, 14],    //20
    [23, 22, 20, 11],
    [20, 21, 23, 26, 28],
    [25, 24, 22, 21],
    [23, 25],
    [23, 24],    //25
    [28, 27, 22, 20, 19],
    [19, 26, 28, 29],
    [29, 27, 26, 22],
    [34, 30, 28, 27],
    [29, 31],   //30
    [33, 32, 30, 18],
    [36, 33, 31, 0],
    [37, 36, 35, 34, 32, 31, 30, 29],
    [29, 33, 35],
    [37, 34, 33], //35
    [32, 33, 37],
    [39, 38, 36, 35, 33],
    [40, 39, 37, 35],
    [37, 38, 40],
    [41, 39, 38],   //40
    [40, 12]
];


$game_id = 1;
$turn = 1;
$amount_players =2;

empty_db($game_id);
# Pick countries
echo "Picking countries: <br />";
for ($i = 0; $i < 42; $i++) {
    $player_id = $i % $amount_players;

    $result_country_id = -1;
    while ($result_country_id == -1) {
        $result_country_id = pick_countries($game_id, $player_id);
    }
    echo $player_id." picked a country: ".$result_country_id."<br />";
    db_pick_country($game_id, $player_id, $result_country_id);
}

function pick_countries($game_id, $player_id){
    
    $max_turn = get_max_turn($game_id);
    $current_country_states = get_country_states($game_id, $max_turn);
    $country_worth = get_countries_worth($game_id, $max_turn, $player_id);

    usort($country_worth, function($a, $b){
        return $b["tot"] <=> $a["tot"];
    });

    if ($player_id == 0 &&
    ($current_country_states[41]["owner"] == -1 ||
    $current_country_states[40]["owner"] == -1 ||
    $current_country_states[39]["owner"] == -1 ||
    $current_country_states[38]["owner"] == -1
    )){
        for ($i = 38; $i < 42; $i++) {
            if ($current_country_states[$i]["owner"] == -1) {
                return $i;
            }
        }
    }

    $total_top_five = 0;
    for ($i = 0; $i < 5; $i++) {
        if ($current_country_states[$i]["owner"] == -1){
            $total_top_five += $country_worth[$i]["tot"];
        }
    }

    $block_country_id = block_continent_bonus($game_id, $player_id, $current_country_states);
    if ($block_country_id != -1) {
        echo "Blocked: ".$block_country_id;
        return $block_country_id;
    }else{
        $result = random_int(0, $total_top_five);
        for ($i = 0; $i < 42; $i++) {
            if ($current_country_states[$country_worth[$i]["id"]]["owner"] == -1) {
                $result -= $country_worth[$i]["tot"];                    
            }

            #print($country_worth[$i]["id"]."<br />");
            
            if (1 > $result && $current_country_states[$country_worth[$i]["id"]]["owner"] == -1) {
                return $country_worth[$i]["id"];
            }
        }
    }

    for ($i = 0; $i < 42; $i++) {
        if ($current_country_states[$i] == -1){
            return $i;
        }
    }
    return -1;
}

function block_continent_bonus($game_id, $player_id, $country_states){
    global $continents;
    global $targets;

    $continent_owners = array(); 
    for ($i = 0; $i < count($continents); $i++) {
        $continent_owners[$i] = array();
        for ($j = 0; $j < count($continents[$i]); $j++) {
            array_push($continent_owners[$i], $country_states[$continents[$i][$j]]["owner"]);
        }
    }

    $countries_to_block_with = array();
    $continent_owners_counted = array();
    for ($i = 0; $i < count($continents); $i++) {
        $continent_owners_counted[$i] = array_count_values($continent_owners[$i]);

        if (isset($continent_owners_counted[$i][-1]) && (
        ($continent_owners_counted[$i][-1] == 1 && count($continent_owners_counted[$i]) == 2) ||
        ($continent_owners_counted[$i][-1] == 2 && count($continent_owners_counted[$i]) == 2)
        )) {
            foreach ($continents[$i] as $country_id) {
                if ($country_states[$country_id]["owner"] == -1) {
                    array_push($countries_to_block_with, $country_id);
                }
            } 
        }
    }

    //print_r($countries_to_block_with);
    //echo "<br />";

    if (count($countries_to_block_with) > 0) {
        return $countries_to_block_with[random_int(0, count($countries_to_block_with) - 1)];
    }else{
        return -1;
    }
}

function get_countries_worth($game_id, $turn, $player_id){
    $country_states = get_country_states($game_id, $turn);

    $countries_worth = array();
    for ($country_id = 0; $country_id < 42; $country_id++) {
        $countries_worth[$country_id] = get_country_worth($game_id, $turn, $player_id, $country_id, $country_states);
    }

    return $countries_worth;
}

function get_country_worth($game_id, $turn, $player_id, $country_id, $country_states){
    global $continents;
    global $continent_bonus;
    global $targets;

    $max_worth_cont = 8;
    $max_worth_target = 8;

    $owned_countries = array();
    for ($i = 0; $i < 42; $i++) {
        if ($country_states[$i]["owner"] == $player_id) {
            array_push($owned_countries, $i);
        }
    }

    $target_own_count = 0;
    for ($target_id = 0; $target_id < count($targets[$country_id]); $target_id++) {
        if (in_array($targets[$country_id][$target_id], $owned_countries)) {
            $target_own_count++;
        }
    }
    $defence_worth = $target_own_count / count($targets[$country_id]) * 100;
    // DEFENCE WORTH

    $amount_soldiers = $country_states[$country_id]["amount_soldiers"];
    $soldier_worth = $amount_soldiers / 9 * 100;
    // SOLDIER WORTH

    $continent_id = -1;
    for ($i = 0; $i < count($continents); $i++) {
        if (in_array($country_id, $continents[$i])) {
            $continent_id = $i;
            break;
        }
    }

    $continent_worth = 0;
    $continent_own_count = 0;
    for ($i = 0; $i < count($continents[$continent_id]); $i++) {
        if (in_array($continents[$continent_id][$i], $owned_countries)) {
            $continent_own_count++;
        }
    }
    $continent_worth = $continent_own_count / count($continents[$continent_id]) * 100;
    // CONTINENT WORTH

    $intrinsic_worth = 0;
    $intrinsic_worth_continents = count($continents[$continent_id]) / 8 * 100;
    $intrinsic_worth_targets = (8 - count($targets[$country_id])) / 8 * 100;
    $intrinsic_worth = ($intrinsic_worth_continents + $intrinsic_worth_targets) / 2;
    // INTRINSIC WORTH

    $total_worth = $defence_worth + $soldier_worth + $continent_worth + $intrinsic_worth;
    $total_worth = $total_worth / 4;

    return ["id" => $country_id, "def" => $defence_worth, "sol" => $soldier_worth, "con" => $continent_worth, "int" => $intrinsic_worth, "tot" => $total_worth];
}




function get_targets($country_id){
    global $connection;

    $targets = array();
    $sql = "SELECT * FROM country_attack_targets WHERE origin_country_id='".$country_id."';";
    if($result = $connection->query($sql)){
        while ($row = $result->fetch_assoc()){
            $target_country_id = $row['target_country_id'];

            echo "IS A TARGET";

            array_push($targets, $target_country_id);
        }
    }
    return $targets;
}

function get_country_states($game_id, $turn){
    global $connection;

    $country_states = array();
    $sql = "SELECT * FROM country_states WHERE game_id='".$game_id."' AND turn='".$turn."';";
    if ($result = $connection->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $country_id = $row["country_id"];
            $owner = $row["owner_player_id"];
            $amount_soldiers = $row["owner_player_id"];
            

            $country_states[$country_id] = ["owner" => $owner, "amount_soldiers" => $amount_soldiers];
        }
    }

    for ($country_id = 0; $country_id < 42; $country_id++) {
        if(!isset($country_states[$country_id])){
            $country_states[$country_id] = ["owner" => -1, "amount_soldiers" => -1];
        }
    }

    return $country_states;
}

function get_max_turn($game_id){
    global $connection;

    $turn = 0;
    $sql = "SELECT MAX(turn) FROM country_states WHERE game_id=".$game_id.";";
    if ($result = $connection->query($sql)) {
        if ($row = $result->fetch_assoc()) {
            $turn = $row['MAX(turn)'];
        }
    }

    return $turn;
}

function db_pick_country($game_id, $player_id, $country_id) {
    global $connection;

    $sql = "INSERT INTO country_states SET country_id=" . $country_id . ", game_id=" . $game_id . ", owner_player_id=" . $player_id . ", turn=0, amount_soldiers=1;";
    if ($result = $connection->query($sql)) {
        return true;
    } else {
        echo " error 124444";
        die();
    }
}

function empty_db($game_id){
    global $connection;

    $sql = "DELETE FROM country_states WHERE game_id=".$game_id;
    $result = $connection->query($sql);

    $sql = "DELETE FROM reinforcements WHERE game_id=".$game_id;
    $result = $connection->query($sql);

    $sql = "DELETE FROM moves WHERE game_id=".$game_id;
    $result = $connection->query($sql);
}