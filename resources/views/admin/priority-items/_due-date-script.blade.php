@push('scripts')
<script>
    /*
     * Keep the displayed default due date in sync with the selected priority
     * while the user is choosing. The server still calculates and validates the
     * authoritative value, so this is only a convenience and never the source
     * of truth. A date the user typed is never overwritten.
     */
    (function () {
        var DAYS = { urgent: 0, high: 1, medium: 3, low: 7 };

        function format(date) {
            var y = date.getFullYear();
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        document.querySelectorAll('[data-priority-due-form]').forEach(function (form) {
            var priority = form.querySelector('[data-priority-select]');
            var due = form.querySelector('[data-due-date-input]');
            if (!priority || !due) {
                return;
            }

            var autoValue = '';

            function applyDefault() {
                var days = DAYS[priority.value];
                if (days === undefined) {
                    return;
                }
                if (due.value && due.value !== autoValue) {
                    return;
                }

                var target = new Date();
                target.setHours(0, 0, 0, 0);
                target.setDate(target.getDate() + days);

                due.value = format(target);
                autoValue = due.value;
            }

            applyDefault();
            priority.addEventListener('change', applyDefault);
        });
    })();
</script>
@endpush
