<?php
/**
 * @var array        $user
 * @var array|null   $org
 * @var bool         $services   the reader's organisation is a service agency
 * @var list<string> $expected   the roles their Properties page has columns for
 * @var string       $supportMail
 * @var bool         $microsoft
 */
$admin = !empty($user['is_admin']);
$orgName = $org['name'] ?? 'your organization';
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1">How the portal works</h1>
        <p class="pp-sub">Everything on one page. Each screen also has a short "How this works" note at the top.</p>
    </div>
</div>
<div class="pp-guide">
    <nav class="pp-guide__nav" aria-label="Sections">
        <a href="#what">What this is for</a>
        <a href="#signin">Signing in</a>
        <a href="#roster">Staff roster</a>
        <a href="#properties">Properties and open positions</a>
        <a href="#history">What WHPC sees</a>
        <?php if ($admin): ?><a href="#admin">For WHPC admins</a><?php endif; ?>
        <a href="#questions">Questions</a>
    </nav>
    <article class="pp-guide__body">
        <section id="what">
            <h2>What this is for</h2>
            <p>WHPC needs to know who is assigned to each of its properties: <?= $services ? 'service coordinators and supervisors' : 'regional managers, site managers, maintenance technicians and the rest of the team' ?>. Instead of emailing spreadsheets, <?= esc($orgName) ?> keeps that list current here. What WHPC already has on file is loaded for you; you only record what changes, and nothing has to be re-keyed on WHPC's side.</p>
            <p>There are two ways to look at the same information:</p>
            <ul>
                <li><strong>Staff roster</strong>: one row per person, with the properties they cover. This is where you add people and record departures.</li>
                <li><strong>Properties</strong>: one row per property, with who holds each role. This is where you see what is OPEN and note who is covering.</li>
            </ul>
        </section>
        <section id="signin">
            <h2>Signing in</h2>
            <ul>
                <li>There is no password. Enter your work email and a six-digit code is emailed to you; type it in, or click the link in the email.</li>
                <?php if ($microsoft): ?><li>WHPC staff sign in with Microsoft instead, using their normal work account.</li><?php endif; ?>
                <li>Only addresses WHPC has set up can sign in. To add a colleague, ask WHPC at <a href="mailto:<?= esc($supportMail, 'attr') ?>"><?= esc($supportMail) ?></a> with their name and work email.</li>
            </ul>
        </section>
        <section id="roster">
            <h2>Staff roster</h2>
            <h3>Someone new starts</h3>
            <ol>
                <li>Open <strong>Staff roster</strong> and click <strong>Add a person</strong>.</li>
                <li>Enter their name as it should appear, choose their <strong>position</strong>, and add their work email and phone. If they have no work email, tick <strong>N/A</strong>.</li>
                <li>Tick every property they cover. Use the filter box to find properties quickly. If their role at one property is different from their position, pick it in the dropdown beside that property.</li>
                <li>Enter their <strong>start date</strong> and save. Any OPEN positions they now fill close by themselves.</li>
            </ol>
            <h3>Someone leaves</h3>
            <ol>
                <li>Find them on the roster and click the edit icon.</li>
                <li>Enter their <strong>end date</strong> (their last day) and save.</li>
                <li>They move to the departed list, and every position they held shows as OPEN on the Properties page as of that date. Nothing is deleted; their history stays.</li>
            </ol>
            <h3>Someone changes properties or role</h3>
            <p>Edit the person and tick or untick properties. A property you untick shows as OPEN until someone else covers it. If a person covers many properties, this is still one edit.</p>
            <h3>Coming back</h3>
            <p>If a departed person returns, edit them and clear the end date. Their properties are restored.</p>
        </section>
        <section id="properties">
            <h2>Properties and open positions</h2>
            <p>The Properties page has a column for each role every property should have filled: <?= esc(implode(', ', $expected)) ?>. In each cell you see either the person's name or <span class="pp-open">OPEN</span>.</p>
            <ul>
                <li><strong>OPEN since a date</strong> means the last person left on that date and nobody has replaced them.</li>
                <li><strong>Covered by</strong> is your note on who is looking after the property in the meantime. Click <strong>Record vacancy</strong> or <strong>Update</strong> to enter it.</li>
                <li>A red OPEN has no coverage note yet. Tick <strong>Needs attention only</strong> to see just those.</li>
                <li>A position that has never had anyone shows the same way; click <strong>Record vacancy</strong> to say since when and who covers.</li>
                <li>To fill an OPEN position, use <strong>Add a person</strong>; the vacancy closes itself when you tick that property.</li>
                <li>The <strong>Also</strong> column lists people at the property in other roles, such as assistant managers or leasing agents.</li>
                <li><strong>Download</strong> gives you a spreadsheet of the current assignments, for your own staff or managers.</li>
            </ul>
            <?php if ($services): ?><p>As a service agency, <?= esc($orgName) ?> can be assigned to any WHPC property. The Properties page lists the ones your people cover; each shows which management company runs it.</p><?php endif; ?>
        </section>
        <section id="history">
            <h2>What WHPC sees</h2>
            <ul>
                <li>Every change is kept with the date it took effect, when it was entered and by whom. The history icon beside each person shows it all.</li>
                <li>You only ever see <?= esc($orgName) ?>'s people. Other organizations see theirs.</li>
                <li>WHPC uses this list to keep its Assets records current, so please enter changes when they happen rather than in batches.</li>
            </ul>
        </section>
        <?php if ($admin): ?>
        <section id="admin">
            <h2>For WHPC admins</h2>
            <ul>
                <li><strong>Portal users</strong>: who may sign in. Add a partner user with their name, work email and organization. A whpc.com address becomes a WHPC admin instead, with no organization. Disable rather than delete anyone who has recorded changes.</li>
                <li><strong>Organizations</strong>: management companies and their properties come from Assets nightly and whenever you click <strong>Sync from Assets</strong>; the same sync imports Assets' property contacts into the rosters. Service agencies such as LSS are added here by hand; their people may cover any property.</li>
                <li><strong>Recent changes</strong>: everything partners have entered, newest first, across every organization.</li>
                <li>On the roster and Properties pages you choose which organization to look at; you can edit on their behalf.</li>
                <li>Two standing admins are fixed in the portal's configuration (the <code>partner.admins</code> secret) so nobody can lock WHPC out; they cannot be disabled here. Every other WHPC admin is managed on Portal users, and a WHPC address that is not on that page cannot sign in.</li>
            </ul>
        </section>
        <?php endif; ?>
        <section id="questions">
            <h2>Questions</h2>
            <p>Email <a href="mailto:<?= esc($supportMail, 'attr') ?>"><?= esc($supportMail) ?></a>. If something on this site looks wrong about a property or a person, say which one and we will sort it out.</p>
        </section>
    </article>
</div>
<?= $this->endSection() ?>
