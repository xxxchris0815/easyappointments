<script>
    window.vars = (function () {
        const vars = <?= json_encode(script_vars()) ?>;

        return (key) => {
            if (!key) {
                return vars;
            }

            if (!Object.prototype.hasOwnProperty.call(vars, key)) {
                return undefined;
            }

            return vars[key];
        };
    })();
</script>

