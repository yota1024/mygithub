<?php
require_once ("common2.php");
$myname = 'nextstage.php';

$SI = new siteInfo();
$myURL = $SI->getUrl() . $myname;
if (strncmp(@$_SERVER['HTTP_REFERER'], $myURL, strlen($myURL))) {
    unset($_POST);
}

$PdoInfo = new PDOInfo();
$pi = $PdoInfo->getPDOInfo();

try {
    $pdo = new PDO($pi[0], $pi[1], $pi[2]);
} catch (PDOException $e) {
    print "エラー!: " . $e->getMessage() . "<br/>";
    die();
}

class Sort
{

    private $sort = 'no';

    private $presort = 'no';

    // private $seq = 'ASC';
    private $seq = 'DESC';

    public function getSort()
    {
        return $this->sort;
    }

    public function setSort($sort)
    {
        $this->sort = $sort;
    }

    public function getPreSort()
    {
        return $this->presort;
    }

    public function setPreSort($presort)
    {
        $this->presort = $presort;
    }

    public function getSeq()
    {
        return $this->seq;
    }

    public function setSeq($seq)
    {
        $this->seq = $seq;
    }

    public function changeSeq()
    {
        if ($this->seq == 'ASC') {
            $this->seq = 'DESC';
        } else {
            $this->seq = 'ASC';
        }
    }
}

class Sort2 extends Sort
{
}

class Action
{

    public function check_exist($pdo, $post)
    {
        $sql = $pdo->prepare('select count(*) from grammar where english = ? AND japanese = ?');
        $ret = $sql->execute(array(
            $post['english'],
            $post['japanese']
        ));
        $this->whenFalse($pdo, $ret);
        return $sql->fetchColumn();
        ;
    }

    public function insert($pdo, $post)
    {
        $insert = "INSERT INTO `grammar` (`no`, `english`, `japanese`, `point`, `priority`, `imageName`) VALUES (NULL, :english, :japanese, :point, :priority, :imageName)";
        $sql = $pdo->prepare($insert);
        $param_array = array(
            'english',
            'japanese',
            'point',
            'priority',
            'imageName'
        );
        for ($i = 0; $i < count($param_array); $i ++) {
            $sql->bindParam(':' . $param_array[$i], $post[$param_array[$i]], PDO::PARAM_STR);
        }
        $ret = $sql->execute();
        $this->whenFalse($pdo, $ret);
    }

    public function delete($pdo, $no)
    {
        $delete = "DELETE FROM `grammar` WHERE `no` = " . $no;
        $sql = $pdo->prepare($delete);
        $ret = $sql->execute();
        $this->whenFalse($pdo, $ret);
    }

    public function select_one($pdo, $no)
    {
        $sql = $pdo->prepare('select * from grammar where no = ?');
        $ret = $sql->execute(array(
            $no
        ));
        $this->whenFalse($pdo, $ret);
        // $result = $sql->fetch();
        return $sql->fetch();
    }

    public function update($pdo, $english, $japanese, $point, $no, $priority, $imageName)
    {
        $sql = $pdo->prepare('UPDATE grammar SET english = :english, japanese = :japanese, point = :point , priority = :priority , imageName = :imageName WHERE no = :no');
        $params = array(
            ':english' => $english,
            ':japanese' => $japanese,
            ':point' => $point,
            ':no' => $no,
            ':priority' => $priority,
            ':imageName' => $imageName
        );
        $ret = $sql->execute($params);
        $this->whenFalse($pdo, $ret);
    }

    public function whenFalse($pdo, $ret)
    {
        if ($ret === false) {
            echo "\nPDO::errorInfo():\n";
            print_r($pdo->errorInfo());
        }
    }
}

class Paging
{

    public $page = 1;

    public $maxNum = 10;

    public function count_number($pdo, $post)
    {
        $sql = $pdo->prepare('select count(*) from grammar');
        $ret = $sql->execute();
        Action::whenFalse($pdo, $ret);
        return $sql->fetchColumn();
    }
}

$priorityArray = array(
    '難しい',
    '忘れがち',
    'まだまだ',
    'もう大丈夫？'
);
$priorityArray[] = '安心';
// print_r($priorityArray);
$action = 'normal';
/**
 * *********登録**********
 */
// print_r($_SERVER);
$SR = new Sort();
$AC = new Action();

// echo "REQUEST_METHOD=".$_SERVER["REQUEST_METHOD"].'<br>';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // printArray($_POST);
    $action = $_POST['action'];
} else if ($_SERVER["REQUEST_METHOD"] === "GET" and array_key_exists('action', $_GET)) {
    // printArray($_GET);
    $action = $_GET['action'];
}
mess('action');

/**
 * *********新規追加**********
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST['action'] == "insert_exec") {
    // echo 'english='.$_POST['english'];
    // 存在チェック
    $count = $AC->check_exist($pdo, $_POST);
    // echo 'count='.$count;
    // 追加
    if ($count == 0) {
        $AC->insert($pdo, $_POST);
        header('Location:' . $myURL, true, 303);
    }

/**
 * *********削除**********
 */
} else if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST['action'] == "delete") {
    $AC->delete($pdo, $_POST['no']);

/**
 * *********変更**********
 */
} else if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST['action'] == "update_exec") {
    $AC->update($pdo, $_POST['english'], $_POST['japanese'], $_POST['point'], $_POST['no'], $_POST['priority'], $_POST['imageName']);

/**
 * *********並び順チェック**********
 */
} else if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["sort"])) {
    if (isset($_GET["presort"])) {
        if (! isset($_GET["seq"]) || $_GET["presort"] !== $_GET["sort"]) {
            $SR->setSeq("ASC");
        } else {
            $SR->setSeq($_GET["seq"]);
        }
        $SR->setSort($_GET["sort"]);
    }
    $SR->setPreSort($SR->getSort());
} else {}

/**
 * *********情報取得**********
 */
$sortSQL = ' ORDER BY `grammar`.`' . $SR->getSort() . '` ' . $SR->getSeq();
$SR->changeSeq();
$sql = $pdo->prepare('select * from grammar' . $sortSQL);
$sql->execute();

// ページング処理
$PG = new Paging();
$num = $sql->rowCount();
$maxPage = ceil($num / $PG->maxNum);
// echo '<br>num='.$num;
// echo '<br>maxPage='.$maxPage;
if (isset($_GET['page'])) {
    $PG->page = $_GET['page'];
}

/**
 * *********表作成**********
 */
$member = '';
$showRowStart = ($PG->page - 1) * $PG->maxNum + 1;
$cnt = 1;
foreach ($sql->fetchAll() as $row) {
    if ($cnt >= $showRowStart and $cnt < ($showRowStart + $PG->maxNum)) {
        // print_r($row);
        // echo 'cnt='.$cnt;
        $fontColorArray = array(
            '#FF0000',
            '#FF00FF',
            '#000080',
            '#000000'
        );
        $row = getHtmlspecialcharsForArray($row);
        $member .= '<tr>';
        $member .= '<td>' . $row['no'] . '</td>';
        $name = addslashes($row['english']);
        // mess('name');
        // $member .= '<td onClick="linkGoogle(\''.$name.'\')">';
        $member .= "<td onClick='linkGoogle(\"" . $name . "\")'>";
        $member .= '<span style="color:' . $fontColorArray[$row['priority']] . ';">' . $row['english'] . '</span></td>';
        $member .= '<td>' . $row['japanese'] . '</td>';
        $member .= '<td>' . $row['point'] . '</td>';
        $member .= '<td><span style="font-size : smaller">' . $priorityArray[$row['priority']] . '</span></td>';
        $member .= '<form action="" method="post" name="submit2"><td>';
        $member .= '<input type="hidden" name="no" value="' . $row['no'] . '">';
        $member .= '<button type="submit" name="action" value="delete" onClick="return confirm(\'本当に削除しますか？\')">削除</button></td>';
        $member .= "<td><button type='submit' name='action' value='update'>変更</button>";
        $member .= '</td></form>';
        $member .= '</tr>' . "\n";
        if (strlen($row['imageName']) > 1) {
            $member .= '<tr>';
            $member .= '<td colspan="7"><img src="../img/' . $row['imageName'] . '"></td>';
            $member .= '</tr>';
        }
    }
    $cnt ++;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title></title>
<script type="text/javascript">
	function linkGoogle(name){
			var win;
			var features = "menubar=yes,location=yes,resizable=yes,scrollbars=yes,status=yes";
			win = window.open("http://www.google.co.jp/search?q=" + escape(name), "google search", features);
	}

	function checkVal(){
		for(var i=0; i<document.forms.submit.length-2;i++){
			if(document.forms.submit[i].value == ''){
				alert(document.forms.submit[i].name + 'が入力されていません');
			}
		}
	}
	//console.log('コンソール出すぜ');
</script>

<body>
<link href="tableNS.css" type="text/css" rel="stylesheet" />
<span style="font : 40pt bolder red">Next Stage</span>
<br><br>
<?php
if ($action != "insert" and $action != "update") {
    ?>
	<!--新規追加ボタン-->
		<table>
			<tr>
				<td>
					<form action="" method="get" name="submit1">
						<button type="submit" name="action" value="insert">新規追加</button>
					</form>
				</td>
			</tr>
		</table>

	<!--本体-->
	<table  id="sample2">
	  <tbody>
	    <tr>
	      <!--<th width="5%"><a href= <?php

echo $myname;
    ?>?sort=no&seq=<?php

echo $SR->getSeq() . '&presort=' . $SR->getPresort()?>>No. </a></th>
	      <th><a href= <?php

echo $myname;
    ?>?sort=english&seq=<?php

echo $SR->getSeq() . '&presort=' . $SR->getPresort()?>>英文 </a></th>
	      <th><a href= <?php

echo $myname;
    ?>?sort=japanese&seq=<?php

echo $SR->getSeq() . '&presort=' . $SR->getPresort()?>>訳 </a></th>
				<th width="20%"><a href= <?php

echo $myname;
    ?>?sort=point&seq=<?php

echo $SR->getSeq() . '&presort=' . $SR->getPresort()?>>ポイント </a></th>-->
				<th width="1%">No. </th>
	      <th>英文 </th>
	      <th>訳 </th>
				<th width="20%">ポイント </th>
				<th width="8%"></th>
				<th width="2%"></th>
				<th width="2%"></th>
	    </tr>
			<?php

echo $member;
    ?>
	  </tbody>
	</table>
	<table>
		<tr>
			<td>
				<?php
    for ($i = 1; $i <= $maxPage; $i ++) {
        if ($i == $PG->page) {
            echo $i . " ";
        } else {
            echo "<a href='nextstage.php?page=" . $i . "'>" . $i . " </a>";
        }
    }
    ?>
			</td>
		</tr>
	</table>
	<br>

<?php
/**
 * *********新規追加***********
 */
} else if ($action == "insert") {
    ?>
	<form action="" method="post" name="submit2">
		<table>
			<tr>
				<td>英文 <textarea name="english" rows="4" cols="40"></textarea></td>
			</tr>
			<tr>
				<td>訳 <textarea name="japanese" rows="4" cols="40"></textarea></td>
			</tr>
			<tr>
				<td>ポイント <textarea name="point" rows="4" cols="40"></textarea></td>
			</tr>
			<tr>
				<td>重要度
					<select name="blood">
						<!--<option value="0"><?

echo $priorityArray[0];
    ?></option>
						<option value="1"><?

echo $priorityArray[1];
    ?></option>
						<option value="2"><?

echo $priorityArray[2];
    ?></option>
						<option value="3"><?

echo $priorityArray[3];
    ?></option>-->
						<?php

$n = 0;
    while ($n < count($priorityArray)) {
        echo '<option value="' . $n . '">' . $priorityArray[$n] . '</option>';
        $n ++;
    }
    ?>
					</select>
				<input type="hidden" name="priority" ></td>
			</tr>
			<tr>
				<td>画像 <input type="file" name="imageName"></td>
			</tr>
			<tr>
				<td>
					<input type="hidden" name="action" value="insert_exec">
					<input type="submit" value="登録" onClick="checkVal()")></td>
			</tr>
		</table>
	</form>

	<?php
/**
 * *********変更***********
 */
} else if ($action == "update") {
    $ret = $AC->select_one($pdo, $_POST['no']);
    ?>
		<form action="" method="post" name="submit2">
			<table>
				<tr>
					<td>英文 <textarea name="english" rows="4" cols="40"><?php

echo $ret['english']?></textarea></td>
				</tr>
				<tr>
					<td>訳 <textarea name="japanese" rows="4" cols="40"><?php

echo $ret['japanese']?></textarea></td>
				</tr>
				<tr>
					<td>ポイント <textarea name="point" rows="4" cols="40"><?php

echo $ret['point']?></textarea></td>
				</tr>
				<tr>
					<td>重要度
						<select name="priority">

						<?php

$n = 0;
    while ($n < count($priorityArray)) {
        echo '<option value="' . $n;
        if ($ret['priority'] == 0)
            echo 'selected="selected"';
        echo '">' . $priorityArray[$n] . '</option>';
        $n ++;
    }
    ?>
						</select>
						</td>
				</tr>
				<tr>
					<td>画像 <input type="file" name="imageName" value=""></td>
				</tr>
				<tr>
					<td>
						<?php

echo $ret['imageName']?><br>
						<img src="../img/<?php

echo $ret['imageName']?>">
					</td>
				</tr>
				
				<tr>
					<td>
						<input type="hidden" name="no" value="<?php

echo $ret['no']?>">
						<input type="hidden" name="action" value="update_exec">
						<input type="submit" value="登録" onClick="checkVal()")></td>
				</tr>
			</table>
		</form>
	<?php
}
?>
</body>
</html>
