<?php
/** File purpose: Request Progress provides shared application logic and presentation helpers. */
$responses=$isOwner?bb_all($pdo,'SELECT rr.*,u.full_name FROM request_responses rr JOIN users u ON u.id=rr.responder_id WHERE request_id=? ORDER BY rr.responded_at DESC',[$id]):[];
$myOffer=bb_one($pdo,'SELECT response FROM request_responses WHERE request_id=? AND responder_id=?',[$id,$user['id']]);
$selected=(int)$request['accepted_by']===(int)$user['id'] && $request['source_type']==='Donor';
?>
<section class="content-card top-gap"><h2>Request progress</h2><p><?= e(request_progress_label($request)) ?> · Outcome: <?= e($request['outcome']) ?></p>
<?php if($myOffer): ?><p>Your response: <strong><?= e($myOffer['response']) ?></strong></p><?php endif; ?>
<?php if($selected && $request['status']==='Accepted'): ?>
<form method="post" class="form-actions" data-confirm="Confirm this donation progress update?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<?php if(!$request['donor_confirmed_at']): ?><button class="button button-primary" name="action" value="confirm">Confirm I Can Donate</button>
<?php elseif(!$request['donor_reported_at']): ?><button class="button button-primary" name="action" value="donated" data-confirm="Report only after actual donation">I Have Donated</button><?php endif; ?>
<?php if(!$request['donor_reported_at']): ?><button class="button button-secondary" name="action" value="withdraw">Withdraw</button><?php endif; ?></form>
<?php if($request['outcome']==='Disputed'): ?><form method="post" class="form-grid top-gap" data-confirm="Confirm that no donation occurred and your earlier report was incorrect?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-wide"><span>Correction reason — only if you did not actually donate</span><textarea name="outcome_note" maxlength="500" required></textarea></label><button class="button button-secondary" name="action" value="correct_report">I Did Not Donate — Correct Report</button></form><?php endif; ?>
<?php elseif(($myOffer['response']??'')==='Interested' && $request['status']==='Pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-secondary" name="action" value="withdraw">Withdraw Interest</button></form><?php endif; ?>
<?php if($isOwner && $request['source_type']==='Donor'): ?>
<h3>Donor responses</h3><p>Interested donors are not reserved until selected. Only one donor can be selected for this one-unit request.</p>
<?php if(!$responses): ?><p>No responses yet.</p><?php endif; ?>
<?php foreach($responses as $offer): ?><article class="review-card"><div><strong><?= e($offer['full_name']) ?></strong><span><?= e($offer['response']) ?></span></div>
<?php if($request['status']==='Pending' && $offer['response']==='Interested'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="candidate_id" value="<?= (int)$offer['responder_id'] ?>"><button class="button button-primary" name="action" value="select">Select This Donor</button></form><?php endif; ?></article><?php endforeach; ?>
<?php if(in_array($request['status'],['Pending','Accepted'],true)): ?><form method="post" class="form-grid top-gap"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-wide"><span>Blood not received — reason</span><textarea name="outcome_note" maxlength="500" required></textarea></label><div class="form-wide"><button class="button button-secondary" name="action" value="not_received">Record Not Received</button></div></form><?php endif; ?>
<?php endif; ?>
<?php if($isOwner || $selected || $isLinkedHospital): ?><h3>Timeline</h3><?php foreach(bb_all($pdo,'SELECT event,created_at FROM request_events WHERE request_id=? ORDER BY id',[$id]) as $event): ?><p><?= e($event['created_at']) ?> · <?= e($event['event']) ?></p><?php endforeach; ?><?php endif; ?>
</section>
<?php if($isOwner && $request['source_type']==='Donor' && in_array($request['status'],['Pending','Accepted'],true)): ?>
<section class="content-card top-gap" data-nearby="<?= (int)$id ?>"><h2>Available donors within 1 km</h2><p>Consenting donors with recently saved locations and matching blood groups. This is straight-line distance from your request point, not live tracking or clinical clearance.</p><button type="button" class="button button-primary">Check Nearby Donors</button><p data-map-message role="status"></p><div data-map-canvas style="height:320px" aria-label="Approximate nearby donor map"></div><ul data-nearby-list></ul></section>
<?php endif; ?>
