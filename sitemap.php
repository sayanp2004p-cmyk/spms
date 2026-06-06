<?php
require_once 'php/db.php';

$userId = $_GET['id'] ?? null;

/* Get all tables */
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while($row = $stmt->fetch(PDO::FETCH_NUM)){
    $tables[] = $row[0];
}

/* Find likely user table */
$userTable = null;

$possibleTables = [
    'users',
    'user',
    'students',
    'student',
    'customers',
    'customer'
];

foreach($possibleTables as $t){
    if(in_array($t,$tables)){
        $userTable = $t;
        break;
    }
}

if(!$userTable){
    die("No user table found.");
}

/* Get primary key */
$pk = 'id';

$columns = $pdo->query("SHOW COLUMNS FROM `$userTable`")->fetchAll();

foreach($columns as $col){
    if($col['Key']=='PRI'){
        $pk = $col['Field'];
        break;
    }
}

/* Get display field */
$nameField = $pk;

foreach(['name','full_name','student_name','username'] as $field){
    foreach($columns as $col){
        if($col['Field']==$field){
            $nameField = $field;
            break 2;
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>User Database Viewer</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f5f5f5;
}
.card{
    border-radius:15px;
}
</style>

</head>
<body>

<div class="container py-4">

<?php if(!$userId): ?>

<div class="card shadow">
<div class="card-header">
<h4>User List</h4>
</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
<th>ID</th>
<th>Name</th>
<th>Action</th>
</tr>

<?php

$users = $pdo->query("SELECT * FROM `$userTable` ORDER BY $pk DESC");

foreach($users as $user):

?>

<tr>
<td><?= htmlspecialchars($user[$pk]) ?></td>

<td><?= htmlspecialchars($user[$nameField]) ?></td>

<td>
<a href="?id=<?= urlencode($user[$pk]) ?>"
   class="btn btn-primary btn-sm">
   View
</a>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>
</div>

<?php else: ?>

<a href="?" class="btn btn-secondary mb-3">
← Back
</a>

<?php

foreach($tables as $table){

    try{

        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();

        $foundField = null;

        foreach($cols as $col){

            $field = strtolower($col['Field']);

            if(
                strpos($field,'user_id')!==false ||
                strpos($field,'student_id')!==false ||
                strpos($field,'customer_id')!==false ||
                $field=='userid'
            ){
                $foundField = $col['Field'];
                break;
            }
        }

        if(!$foundField){
            continue;
        }

        $stmt = $pdo->prepare(
            "SELECT * FROM `$table`
             WHERE `$foundField`=?"
        );

        $stmt->execute([$userId]);

        $rows = $stmt->fetchAll();

        if(!$rows){
            continue;
        }

        echo '<div class="card shadow mb-4">';
        echo '<div class="card-header">';
        echo '<h5>'.$table.'</h5>';
        echo '</div>';
        echo '<div class="card-body">';

        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered">';

        echo '<tr>';

        foreach(array_keys($rows[0]) as $col){
            echo '<th>'.htmlspecialchars($col).'</th>';
        }

        echo '</tr>';

        foreach($rows as $row){

            echo '<tr>';

            foreach($row as $value){

                echo '<td>'.
                     htmlspecialchars((string)$value).
                     '</td>';
            }

            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

    }catch(Exception $e){
        continue;
    }
}

?>

<?php endif; ?>

</div>

</body>
</html>