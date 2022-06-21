<?php
/**
 * Creates an HTML navigation menu.
 *
 * @param mixed[] $entries Entries to show, each consisting of a 3-tuple:
 *                           1. Name
 *                           2. Action area name
 *                           3. Function to execute upon click (optional)
 * @returns string HTML code.
 */
function mkHtmlNavMenu($entries, $add_undo_redo_buttons = false) {
  ?>
  <div class="menu">
    <div class="dropdown">
      <a id="menu-top-button" href="#"><?= LNG_MENU ?></a>
      <div class="dropdown-content">
        <?php
        foreach ($entries as $e) {
          if (count($e) >= 2) {
            $click_actions = "showActionInput('$e[1]');";
            if (count($e) == 3) {
              $click_actions = "$e[2]; $click_actions";
            }
            ?>
            <a href="#" onclick="<?= $click_actions ?>">
              <?= $e[0] ?>
            </a>
            <?php
          }
          else if (count($e) == 0) {
            ?>
            <div class="sep"><div></div></div>
            <?php
          }
          else {
            die("invalid argument: " . var_dump($e));
          }
        }
        if (count($entries) > 0) {
          ?>
          <div class="sep"><div></div></div>
          <?php
        }
        ?>
        <a href="/app/logout" class="logout"><?= LNG_MENU_LOGOUT ?></a>
      </div>
    </div>
    <?php
    if ($add_undo_redo_buttons) {
      ?>
    <a class="undo-redo disabled" id="undoBtn" onclick="performUndo()"
       href="#" title="<?= LNG_DESC_UNDO ?>">
      &#10150;
    </a><!-- No whitespace
 --><a class="undo-redo disabled" id="redoBtn" onclick="performRedo()"
       href="#" title="<?= LNG_DESC_REDO ?>">
      &#10150;
    </a>
      <?php
    }
    ?>
    <span class="saving-status"></span>
  </div>
  <?php
}
?>
