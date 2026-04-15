/* FramePress — FAQ accordion toggle */
function fpFaqToggle(btn) {
    var expanded = btn.getAttribute('aria-expanded') === 'true';
    var answerId = btn.getAttribute('aria-controls');
    var answer   = document.getElementById(answerId);
    if (!answer) return;

    btn.setAttribute('aria-expanded', String(!expanded));
    if (expanded) {
        answer.hidden = true;
    } else {
        answer.hidden = false;
    }
}
