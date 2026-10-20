<footer class="footer d-print-none rm-footer">
    <span>Copyright &copy; {{ date('Y') }}, Development by <a href="https://merdekacoppergold.com/" target="_blank"
            rel="noopener">Merdeka Group</a></span>
    @if (filled(config('app.version')))
        <a href="/" class="rm-version" title="Application version">v{{ config('app.version') }}</a>
    @endif
</footer>
