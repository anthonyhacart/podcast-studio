import { Controller } from '@hotwired/stimulus';

/**
 * Checkboxes of which exactly `count` must be ticked: once they are, the others cannot
 * be, and until then the browser does not send the form and shows `message`.
 */
export default class extends Controller {
    static values = { count: Number, message: String };

    connect() {
        this.check();
    }

    check() {
        const boxes = [...this.element.querySelectorAll('input[type=checkbox]')];
        const ticked = boxes.filter((box) => box.checked).length;

        boxes.forEach((box) => {
            box.disabled = !box.checked && ticked >= this.countValue;
        });
        // the browser shows it next to the first box when the form is sent
        boxes[0]?.setCustomValidity(ticked === this.countValue ? '' : this.messageValue);
    }
}
