<?php
if (!isset($layout, $active)) {
    return;
}
$links = $layout['links'] ?? [];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="img/labs_logo.png" alt="LaBS PPMKCP" class="sidebar-logo">
    </div>
    <nav class="sidebar-nav">
        <p class="sidebar-title"><?php echo htmlspecialchars($layout['title'] ?? 'Menu'); ?></p>
        <?php foreach ($links as $link): ?>
            <?php
            $is_active = $active === ($link['key'] ?? '');
            $href = $link['href'] ?? '#';
            $children = $link['children'] ?? [];
            ?>
            <?php if ($children): ?>
                <?php
                $group_open = false;
                foreach ($children as $child) {
                    if ($active === ($child['key'] ?? '')) {
                        $group_open = true;
                        break;
                    }
                }
                ?>
                <details class="nav-group"<?php echo $group_open ? ' open' : ''; ?>>
                    <summary class="nav-group-title">
                        <span class="nav-icon"><?php echo $link['icon'] ?? ''; ?></span>
                        <span class="nav-text"><?php echo htmlspecialchars($link['label'] ?? ''); ?></span>
                        <span class="nav-caret" aria-hidden="true">›</span>
                    </summary>
                    <div class="nav-sub">
                        <?php foreach ($children as $child): ?>
                            <?php
                            $child_active = $active === ($child['key'] ?? '');
                            $child_href = $child['href'] ?? '#';
                            ?>
                            <a href="<?php echo htmlspecialchars($child_href); ?>" class="nav-link nav-sublink<?php echo $child_active ? ' active' : ''; ?>">
                                <span class="nav-text"><?php echo htmlspecialchars($child['label'] ?? ''); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($href); ?>" class="nav-link<?php echo $is_active ? ' active' : ''; ?>">
                    <span class="nav-icon"><?php echo $link['icon'] ?? ''; ?></span>
                    <span class="nav-text"><?php echo htmlspecialchars($link['label'] ?? ''); ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="sidebar-card">
            <h3>LaBS PPMKCP</h3>
            <p>Pejabat Pengurusan Makmal Kampus Cawangan Pagoh</p>
            <p>Telefon: 06-974 2116</p>
            <p>Email: ppmkcp@uthm.edu.my</p>
        </div>
    </nav>
</aside>
