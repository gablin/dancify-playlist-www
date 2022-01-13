<?php
/**
 * Creates an HTML navigation menu.
 *
 * @param mixed[] $entries Entries to show, each consisting of a 3-tuple:
 *                           1. Name
 *                           2. HTTP link path
 *                           3. action area name (optional)
 * @returns string HTML code.
 */
function mkHtmlNavMenu($entries) {
  ?>
  <div class="menu">
    <div class="dropdown">
      <a href="#"><?= LNG_MENU ?></a>
      <div class="dropdown-content">
        <?php
        foreach ($entries as $e) {
          if (count($e) == 2) {
            ?>
            <a href="<?= $e[1] ?>"><?= $e[0] ?></a>
            <?php
          }
          else if (count($e) == 3) {
            ?>
            <a href="<?= $e[1] ?>" onclick="showActionInput('<?= $e[2] ?>');">
              <?= $e[0] ?>
            </a>
            <?php
          }
          else {
            die("invalid argument: " . var_dump($e));
          }
        }
        ?>
        <a href="/app/logout" class="logout"><?= LNG_MENU_LOGOUT ?></a>
      </div>
    </div>
    <span class="saving-status"></span>
  </div>
  <?php
}
?>
