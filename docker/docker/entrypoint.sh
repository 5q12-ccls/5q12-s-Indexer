#!/bin/sh

echo "Starting 5q12's Indexer..."

# Set timezone if provided
if [ ! -z "$TZ" ]; then
    echo "Setting timezone to $TZ"
    ln -sf /usr/share/zoneinfo/$TZ /etc/localtime
    echo $TZ > /etc/timezone
fi

# Ensure mount point directories exist
mkdir -p /config
mkdir -p /files

# Ensure required config subdirectories exist
echo "Ensuring config subdirectories exist..."
mkdir -p /config/zip_cache
mkdir -p /config/index_cache
mkdir -p /config/icons
mkdir -p /config/favicon

# CRITICAL: Always force-refresh local_api directory on every startup
# This ensures backend files are always compatible with current index.php
echo "Force-refreshing local_api directory (critical backend files)..."
rm -rf /config/local_api

if [ -d "/app/default-config/local_api" ]; then
    cp -r /app/default-config/local_api /config/
    echo "✓ local_api directory refreshed from defaults"
else
    # Fallback: create minimal structure if default not found
    mkdir -p /config/local_api/style
    echo "⚠ Created minimal local_api structure (default not found)"
fi

# Handle config.json with version checking and field merging
echo "Checking config.json version and updating if needed..."

# Function to merge JSON configs using jq-like shell operations
merge_config_json() {
    local existing_config="$1"
    local default_config="$2"
    local output_config="$3"
    
    # Create a temporary script to merge configs
    cat > /tmp/merge_config.php << 'MERGE_EOF'
<?php
$existing = json_decode(file_get_contents($argv[1]), true);
$default = json_decode(file_get_contents($argv[2]), true);

if (!$existing || !$default) {
    echo "Error: Could not parse JSON files\n";
    exit(1);
}

// Function to recursively merge arrays, adding missing keys from default
function merge_recursive($existing, $default) {
    $result = $existing;
    
    foreach ($default as $key => $value) {
        if (!array_key_exists($key, $result)) {
            // Key is missing in existing config, add it
            $result[$key] = $value;
            echo "Added missing config field: $key\n";
        } elseif (is_array($value) && is_array($result[$key])) {
            // Both are arrays, merge recursively
            $result[$key] = merge_recursive($result[$key], $value);
        }
        // If key exists and is not an array, keep existing value
    }
    
    return $result;
}

$merged = merge_recursive($existing, $default);

// Always update version to match default
$existing_version = $existing['version'] ?? 'unknown';
$default_version = $default['version'] ?? 'unknown';

if ($existing_version !== $default_version) {
    echo "Updating version from $existing_version to $default_version\n";
    $merged['version'] = $default_version;
}

// Write merged config
file_put_contents($argv[3], json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Config merge completed successfully\n";
MERGE_EOF

    # Run the merge script
    php /tmp/merge_config.php "$existing_config" "$default_config" "$output_config"
    local merge_result=$?
    
    # Clean up temporary script
    rm -f /tmp/merge_config.php
    
    return $merge_result
}

if [ -f "/app/default-config/config.json" ]; then
    if [ -f "/config/config.json" ]; then
        echo "Existing config.json found, checking for updates..."
        
        # Create backup of existing config
        cp /config/config.json /config/config.json.backup
        
        # Attempt to merge configs
        if merge_config_json "/config/config.json" "/app/default-config/config.json" "/config/config.json.new"; then
            # Replace the existing config with merged version
            mv /config/config.json.new /config/config.json
            echo "✓ Config.json updated with missing fields and latest version"
        else
            echo "⚠ Config merge failed, keeping existing config.json"
            # Restore backup if merge failed
            if [ -f "/config/config.json.backup" ]; then
                mv /config/config.json.backup /config/config.json
            fi
        fi
        
        # Clean up any leftover files
        rm -f /config/config.json.new /config/config.json.backup
    else
        echo "No existing config.json, copying default..."
        cp /app/default-config/config.json /config/config.json
        echo "✓ Default config.json copied"
    fi
else
    echo "⚠ No default config.json found"
    
    # Create config.json if neither exists
    if [ ! -f "/config/config.json" ]; then
        echo "Creating minimal config.json..."
        cat > /config/config.json << 'EOF'
{
    "version": "1.1.18",
    "main": {
        "access_url": "",
        "cache_type": "sqlite",
        "icon_type": "default",
        "disable_file_downloads": false,
        "disable_folder_downloads": false,
        "index_hidden": false,
        "index_all": false,
        "deny_list": "",
        "allow_list": ""
    },
    "exclusions": {
        "index_non-descript-files": true,
        "index_php": false,
        "index_js": true,
        "index_md": true,
        "index_txt": true,
        "index_py": true,
        "index_powershell": true,
        "index_html": true,
        "index_css": true,
        "index_json": true,
        "index_rar": true,
        "index_zip": true,
        "index_7z": true,
        "index_yml": true,
        "index_conf": true,
        "index_ini": true,
        "index_bat": true,
        "index_sh": true,
        "index_png": true,
        "index_jpg": true,
        "index_mp4": true,
        "index_mp3": true,
        "index_flac": true,
        "index_pdf": true,
        "index_sql": true,
        "index_xml": true,
        "index_log": true,
        "index_env": true,
        "index_docx": true,
        "index_xlsx": true,
        "index_exe": true,
        "index_iso": true,
        "index_gif": true,
        "index_jfif": true,
        "index_mkv": true,
        "index_webp": true,
        "index_folders": true,
        "index_ada": true,
        "index_adb": true,
        "index_ads": true,
        "index_asm": true,
        "index_s": true,
        "index_c": true,
        "index_clj": true,
        "index_cob": true,
        "index_cbl": true,
        "index_coffee": true,
        "index_cpp": true,
        "index_cs": true,
        "index_dart": true,
        "index_dfm": true,
        "index_dpr": true,
        "index_elm": true,
        "index_f": true,
        "index_f90": true,
        "index_f95": true,
        "index_for": true,
        "index_fortran": true,
        "index_fs": true,
        "index_go": true,
        "index_groovy": true,
        "index_haskell": true,
        "index_hs": true,
        "index_h": true,
        "index_java": true,
        "index_class": true,
        "index_jar": true,
        "index_kt": true,
        "index_less": true,
        "index_lisp": true,
        "index_lsp": true,
        "index_lua": true,
        "index_matlab": true,
        "index_ml": true,
        "index_m": true,
        "index_ocaml": true,
        "index_pas": true,
        "index_pp": true,
        "index_pl": true,
        "index_pro": true,
        "index_prolog": true,
        "index_jsx": true,
        "index_r": true,
        "index_rb": true,
        "index_rs": true,
        "index_sass": true,
        "index_scala": true,
        "index_scm": true,
        "index_scheme": true,
        "index_scss": true,
        "index_stylus": true,
        "index_swift": true,
        "index_ts": true,
        "index_tsx": true,
        "index_vb": true,
        "index_vue": true,
        "index_haml": true,
        "index_pug": true,
        "index_jade": true,
        "index_ejs": true,
        "index_handlebars": true,
        "index_mustache": true,
        "index_svelte": true,
        "index_astro": true,
        "index_toml": true,
        "index_properties": true,
        "index_cfg": true,
        "index_settings": true,
        "index_plist": true,
        "index_reg": true,
        "index_sqlite": true,
        "index_db": true,
        "index_mdb": true,
        "index_dbf": true,
        "index_parquet": true,
        "index_avro": true,
        "index_protobuf": true,
        "index_thrift": true,
        "index_rtf": true,
        "index_odt": true,
        "index_ods": true,
        "index_odp": true,
        "index_pages": true,
        "index_numbers": true,
        "index_keynote": true,
        "index_tex": true,
        "index_bib": true,
        "index_markdown": true,
        "index_epub": true,
        "index_mobi": true,
        "index_azw": true,
        "index_fb2": true,
        "index_svg": true,
        "index_ai": true,
        "index_psd": true,
        "index_sketch": true,
        "index_fig": true,
        "index_xcf": true,
        "index_tiff": true,
        "index_bmp": true,
        "index_ico": true,
        "index_cur": true,
        "index_avif": true,
        "index_heic": true,
        "index_raw": true,
        "index_aac": true,
        "index_m4a": true,
        "index_ogg": true,
        "index_opus": true,
        "index_wma": true,
        "index_3gp": true,
        "index_flv": true,
        "index_m4v": true,
        "index_mov": true,
        "index_webm": true,
        "index_wmv": true,
        "index_dng": true,
        "index_bz2": true,
        "index_cab": true,
        "index_gz": true,
        "index_lz": true,
        "index_lzma": true,
        "index_tar": true,
        "index_xz": true,
        "index_deb": true,
        "index_dmg": true,
        "index_msi": true,
        "index_pkg": true,
        "index_rpm": true,
        "index_a": true,
        "index_app": true,
        "index_bin": true,
        "index_dll": true,
        "index_dylib": true,
        "index_elf": true,
        "index_hex": true,
        "index_lib": true,
        "index_obj": true,
        "index_so": true,
        "index_awk": true,
        "index_bashrc": true,
        "index_emacs": true,
        "index_nano": true,
        "index_profile": true,
        "index_psd1": true,
        "index_psm1": true,
        "index_pwsh": true,
        "index_sed": true,
        "index_tcl": true,
        "index_tk": true,
        "index_vbs": true,
        "index_vim": true,
        "index_wsf": true,
        "index_wsh": true,
        "index_zshrc": true,
        "index_ant": true,
        "index_httpd": true,
        "index_apache": true,
        "index_babel": true,
        "index_build": true,
        "index_caddy": true,
        "index_cmake": true,
        "index_cmakelist": true,
        "index_key": false,
        "index_cypress": true,
        "index_cert": true,
        "index_docker_compose": true,
        "index_dockerfile": true,
        "index_dockerignore": true,
        "index_dotenv": true,
        "index_eslint": true,
        "index_gitattributes": true,
        "index_gitconfig": true,
        "index_gitignore": true,
        "index_gradle": true,
        "index_gruntfile": true,
        "index_gulpfile": true,
        "index_htaccess": true,
        "index_jest": true,
        "index_lighttpd": true,
        "index_makefile": true,
        "index_make": true,
        "index_mk": true,
        "index_maven": true,
        "index_nginx": true,
        "index_parcel": true,
        "index_prettier": true,
        "index_rollup": true,
        "index_scons": true,
        "index_sconstruct": true,
        "index_secret": false,
        "index_aliases": true,
        "index_exports": true,
        "index_functions": true,
        "index_traefik": true,
        "index_vagrant": true,
        "index_vagrantfile": true,
        "index_vite": true,
        "index_webpack": true,
        "index_ca_bundle": true,
        "index_p7b": true,
        "index_p7c": true,
        "index_crt": true,
        "index_pem": true,
        "index_cer": true,
        "index_der": true,
        "index_csr": true,
        "index_crl": true,
        "index_crontab": true,
        "index_efi": true,
        "index_uefi": true,
        "index_grub": true,
        "index_lilo": true,
        "index_syslinux": true,
        "index_hosts": true,
        "index_resolv": true,
        "index_jks": false,
        "index_keystore": false,
        "index_truststore": false,
        "index_p12": false,
        "index_pfx": false,
        "index_passwd": false,
        "index_shadow": false,
        "index_group": false,
        "index_sudoers": false,
        "index_rsa": false,
        "index_dsa": false,
        "index_ecdsa": false,
        "index_ed25519": false,
        "index_openssh": false,
        "index_authorized_keys": false,
        "index_known_hosts": false,
        "index_systemd": true,
        "index_service": true,
        "index_socket": true,
        "index_mount": true,
        "index_target": true,
        "index_path": true,
        "index_slice": true,
        "index_scope": true,
        "index_device": true,
        "index_swap": true,
        "index_automount": true,
        "index_timer": true,
        "index_ascii": true,
        "index_audit": true,
        "index_bash_history": true,
        "index_bios": true,
        "index_buffer": true,
        "index_cache": true,
        "index_charset": true,
        "index_core": true,
        "index_crash": true,
        "index_debug": true,
        "index_dram": true,
        "index_eeprom": true,
        "index_encoding": true,
        "index_eprom": true,
        "index_error": true,
        "index_eventlog": true,
        "index_firmware": true,
        "index_fish_history": true,
        "index_flash": true,
        "index_heap": true,
        "index_hibernation": true,
        "index_history": true,
        "index_info": true,
        "index_inputrc": true,
        "index_journal": true,
        "index_kdump": true,
        "index_locale": true,
        "index_dump": true,
        "index_minidump": true,
        "index_nvram": true,
        "index_pagefile": true,
        "index_readline": true,
        "index_rom": true,
        "index_screen": true,
        "index_sram": true,
        "index_stack": true,
        "index_swapfile": true,
        "index_syslog": true,
        "index_temp": true,
        "index_termcap": true,
        "index_terminfo": true,
        "index_tmp": true,
        "index_tmux": true,
        "index_trace": true,
        "index_utf8": true,
        "index_verbose": true,
        "index_vimrc": true,
        "index_vmcore": true,
        "index_warn": true,
        "index_zsh_history": true,
        "index_ansi": true,
        "index_appimage": true,
        "index_apt": true,
        "index_big5": true,
        "index_bower": true,
        "index_component": true,
        "index_composer": true,
        "index_conda": true,
        "index_cp1252": true,
        "index_desktop": true,
        "index_dpkg": true,
        "index_mix": true,
        "index_elm_package": true,
        "index_emerge": true,
        "index_euc": true,
        "index_flatpak": true,
        "index_gb2312": true,
        "index_guix": true,
        "index_mod": true,
        "index_sum": true,
        "index_cabal": true,
        "index_brew": true,
        "index_latin1": true,
        "index_port": true,
        "index_magic": true,
        "index_manifest": true,
        "index_mime": true,
        "index_nix": true,
        "index_npm": true,
        "index_package": true,
        "index_pacman": true,
        "index_pip": true,
        "index_pipfile": true,
        "index_pnpm": true,
        "index_poetry": true,
        "index_pyproject": true,
        "index_requirements": true,
        "index_gem": true,
        "index_cargo": true,
        "index_sbt": true,
        "index_setup": true,
        "index_shift_jis": true,
        "index_snap": true,
        "index_unicode": true,
        "index_urpmi": true,
        "index_yarn": true,
        "index_yum": true,
        "index_zypper": true,
        "index_apollo": true,
        "index_application_properties": true,
        "index_bookshelf": true,
        "index_bootstrap_properties": true,
        "index_bunyan": true,
        "index_commons_logging": true,
        "index_cors": true,
        "index_express": true,
        "index_fastify": true,
        "index_feathers": true,
        "index_gatsby": true,
        "index_gradle_properties": true,
        "index_graphql": true,
        "index_hapi": true,
        "index_helmet": true,
        "index_knex": true,
        "index_koa": true,
        "index_lein": true,
        "index_local_properties": true,
        "index_log4j": true,
        "index_logback": true,
        "index_loguru": true,
        "index_meteor": true,
        "index_mongoose": true,
        "index_morgan": true,
        "index_nest": true,
        "index_next": true,
        "index_nuxt": true,
        "index_objection": true,
        "index_pino": true,
        "index_prisma": true,
        "index_project": true,
        "index_rebar": true,
        "index_relay": true,
        "index_restify": true,
        "index_sails": true,
        "index_sequelize": true,
        "index_slf4j": true,
        "index_socketio": true,
        "index_sveltekit": true,
        "index_tinylog": true,
        "index_typeorm": true,
        "index_vcpp": true,
        "index_solution": true,
        "index_waterline": true,
        "index_websocket": true,
        "index_winston": true,
        "index_workspace": true,
        "index_pbxproj": true,
        "index_xcodeproj": true,
        "index_backstop": true,
        "index_blitz": true,
        "index_chromatic": true,
        "index_concrete5": true,
        "index_craft": true,
        "index_differ": true,
        "index_docusaurus": true,
        "index_doxygen": true,
        "index_drupal": true,
        "index_gemini": true,
        "index_ghost": true,
        "index_gitbook": true,
        "index_grav": true,
        "index_gridsome": true,
        "index_hermione": true,
        "index_hexo": true,
        "index_hugo": true,
        "index_jekyll": true,
        "index_joomla": true,
        "index_jsdoc": true,
        "index_kirby": true,
        "index_looks_same": true,
        "index_magento": true,
        "index_mkdocs": true,
        "index_modx": true,
        "index_nightwatch": true,
        "index_opencart": true,
        "index_oscommerce": true,
        "index_oxid": true,
        "index_percy": true,
        "index_pixelmatch": true,
        "index_prestashop": true,
        "index_protractor": true,
        "index_redwood": true,
        "index_remix": true,
        "index_resemblejs": true,
        "index_shopify": true,
        "index_sphinx": true,
        "index_statamic": true,
        "index_storybook": true,
        "index_strapi": true,
        "index_textpattern": true,
        "index_typedoc": true,
        "index_typo3": true,
        "index_vuepress": true,
        "index_webdriverio": true,
        "index_woocommerce": true,
        "index_wordpress": true,
        "index_wraith": true,
        "index_zencart": true,
        "index_aircrack": true,
        "index_amass": true,
        "index_brave": true,
        "index_burp": true,
        "index_casper": true,
        "index_censys": true,
        "index_charles": true,
        "index_chrome": true,
        "index_curl": true,
        "index_dirb": true,
        "index_dnsrecon": true,
        "index_edge": true,
        "index_ffuf": true,
        "index_fiddler": true,
        "index_fierce": true,
        "index_firefox": true,
        "index_gobuster": true,
        "index_hashcat": true,
        "index_headless": true,
        "index_httpie": true,
        "index_hydra": true,
        "index_ie": true,
        "index_insomnia": true,
        "index_john": true,
        "index_lynx": true,
        "index_maltego": true,
        "index_massdns": true,
        "index_metasploit": true,
        "index_nessus": true,
        "index_nikto": true,
        "index_nmap": true,
        "index_opera": true,
        "index_paw": true,
        "index_phantom": true,
        "index_postman": true,
        "index_safari": true,
        "index_shodan": true,
        "index_slimerjs": true,
        "index_sqlmap": true,
        "index_subfinder": true,
        "index_sublist3r": true,
        "index_tcpdump": true,
        "index_testcafe": true,
        "index_theharvester": true,
        "index_tor": true,
        "index_vivaldi": true,
        "index_w3m": true,
        "index_wget": true,
        "index_wireshark": true,
        "index_zap": true
    },
    "viewable_files": {
        "view_non-descript-files": false,
        "view_php": false,
        "view_js": true,
        "view_md": true,
        "view_txt": true,
        "view_py": true,
        "view_powershell": true,
        "view_html": true,
        "view_css": true,
        "view_json": true,
        "view_yml": true,
        "view_conf": true,
        "view_ini": true,
        "view_bat": true,
        "view_sh": true,
        "view_sql": true,
        "view_xml": true,
        "view_log": true,
        "view_env": true,
        "view_png": true,
        "view_jpg": true,
        "view_mp4": true,
        "view_mp3": true,
        "view_flac": true,
        "view_pdf": true,
        "view_docx": true,
        "view_xlsx": true,
        "view_exe": false,
        "view_iso": false,
        "view_gif": true,
        "view_jfif": true,
        "view_mkv": true,
        "view_webp": true,
        "view_ada": true,
        "view_adb": true,
        "view_ads": true,
        "view_asm": true,
        "view_s": true,
        "view_c": true,
        "view_clj": true,
        "view_cob": true,
        "view_cbl": true,
        "view_coffee": true,
        "view_cpp": true,
        "view_cs": true,
        "view_dart": true,
        "view_dfm": true,
        "view_dpr": true,
        "view_elm": true,
        "view_f": true,
        "view_f90": true,
        "view_f95": true,
        "view_for": true,
        "view_fortran": true,
        "view_fs": true,
        "view_go": true,
        "view_groovy": true,
        "view_haskell": true,
        "view_hs": true,
        "view_h": true,
        "view_java": true,
        "view_class": false,
        "view_jar": false,
        "view_kt": true,
        "view_less": true,
        "view_lisp": true,
        "view_lsp": true,
        "view_lua": true,
        "view_matlab": true,
        "view_ml": true,
        "view_m": true,
        "view_ocaml": true,
        "view_pas": true,
        "view_pp": true,
        "view_pl": true,
        "view_pro": true,
        "view_prolog": true,
        "view_jsx": true,
        "view_r": true,
        "view_rb": true,
        "view_rs": true,
        "view_sass": true,
        "view_scala": true,
        "view_scm": true,
        "view_scheme": true,
        "view_scss": true,
        "view_stylus": true,
        "view_swift": true,
        "view_ts": true,
        "view_tsx": true,
        "view_vb": true,
        "view_vue": true,
        "view_haml": true,
        "view_pug": true,
        "view_jade": true,
        "view_ejs": true,
        "view_handlebars": true,
        "view_mustache": true,
        "view_svelte": true,
        "view_astro": true,
        "view_toml": true,
        "view_properties": true,
        "view_cfg": true,
        "view_settings": true,
        "view_plist": true,
        "view_reg": true,
        "view_sqlite": false,
        "view_db": false,
        "view_mdb": false,
        "view_dbf": false,
        "view_parquet": false,
        "view_avro": true,
        "view_protobuf": true,
        "view_thrift": true,
        "view_rtf": false,
        "view_odt": false,
        "view_ods": false,
        "view_odp": false,
        "view_pages": false,
        "view_numbers": false,
        "view_keynote": false,
        "view_tex": true,
        "view_bib": true,
        "view_markdown": true,
        "view_epub": false,
        "view_mobi": false,
        "view_azw": false,
        "view_fb2": false,
        "view_svg": true,
        "view_ai": false,
        "view_psd": false,
        "view_sketch": false,
        "view_fig": false,
        "view_xcf": false,
        "view_tiff": true,
        "view_bmp": true,
        "view_ico": true,
        "view_cur": true,
        "view_avif": true,
        "view_heic": true,
        "view_raw": false,
        "view_aac": true,
        "view_m4a": true,
        "view_ogg": true,
        "view_opus": true,
        "view_wma": true,
        "view_3gp": true,
        "view_flv": true,
        "view_m4v": true,
        "view_mov": true,
        "view_webm": true,
        "view_wmv": true,
        "view_dng": false,
        "view_bz2": false,
        "view_cab": false,
        "view_gz": false,
        "view_lz": false,
        "view_lzma": false,
        "view_tar": false,
        "view_xz": false,
        "view_deb": false,
        "view_dmg": false,
        "view_msi": false,
        "view_pkg": false,
        "view_rpm": false,
        "view_a": false,
        "view_app": false,
        "view_bin": false,
        "view_dll": false,
        "view_dylib": false,
        "view_elf": false,
        "view_hex": true,
        "view_lib": false,
        "view_obj": false,
        "view_so": false,
        "view_awk": true,
        "view_bashrc": true,
        "view_emacs": true,
        "view_nano": true,
        "view_profile": true,
        "view_psd1": true,
        "view_psm1": true,
        "view_pwsh": true,
        "view_sed": true,
        "view_tcl": true,
        "view_tk": true,
        "view_vbs": true,
        "view_vim": true,
        "view_wsf": true,
        "view_wsh": true,
        "view_zshrc": true,
        "view_ant": true,
        "view_httpd": true,
        "view_apache": true,
        "view_babel": true,
        "view_build": true,
        "view_caddy": true,
        "view_cmake": true,
        "view_cmakelist": true,
        "view_key": false,
        "view_cypress": true,
        "view_cert": false,
        "view_docker_compose": true,
        "view_dockerfile": true,
        "view_dockerignore": true,
        "view_dotenv": true,
        "view_eslint": true,
        "view_gitattributes": true,
        "view_gitconfig": true,
        "view_gitignore": true,
        "view_gradle": true,
        "view_gruntfile": true,
        "view_gulpfile": true,
        "view_htaccess": true,
        "view_jest": true,
        "view_lighttpd": true,
        "view_makefile": true,
        "view_make": true,
        "view_mk": true,
        "view_maven": true,
        "view_nginx": true,
        "view_parcel": true,
        "view_prettier": true,
        "view_rollup": true,
        "view_scons": true,
        "view_sconstruct": true,
        "view_secret": false,
        "view_aliases": true,
        "view_exports": true,
        "view_functions": true,
        "view_traefik": true,
        "view_vagrant": true,
        "view_vagrantfile": true,
        "view_vite": true,
        "view_webpack": true,
        "view_ca_bundle": false,
        "view_p7b": false,
        "view_p7c": false,
        "view_crt": false,
        "view_pem": false,
        "view_cer": false,
        "view_der": false,
        "view_csr": false,
        "view_crl": false,
        "view_crontab": true,
        "view_efi": false,
        "view_uefi": false,
        "view_grub": true,
        "view_lilo": true,
        "view_syslinux": true,
        "view_hosts": true,
        "view_resolv": true,
        "view_jks": false,
        "view_keystore": false,
        "view_truststore": false,
        "view_p12": false,
        "view_pfx": false,
        "view_passwd": false,
        "view_shadow": false,
        "view_group": false,
        "view_sudoers": false,
        "view_rsa": false,
        "view_dsa": false,
        "view_ecdsa": false,
        "view_ed25519": false,
        "view_openssh": false,
        "view_authorized_keys": false,
        "view_known_hosts": false,
        "view_systemd": true,
        "view_service": true,
        "view_socket": true,
        "view_mount": true,
        "view_target": true,
        "view_path": true,
        "view_slice": true,
        "view_scope": true,
        "view_device": true,
        "view_swap": true,
        "view_automount": true,
        "view_timer": true,
        "view_ascii": true,
        "view_audit": true,
        "view_bash_history": true,
        "view_bios": false,
        "view_buffer": false,
        "view_cache": false,
        "view_charset": true,
        "view_core": false,
        "view_crash": false,
        "view_debug": true,
        "view_dram": false,
        "view_eeprom": false,
        "view_encoding": true,
        "view_eprom": false,
        "view_error": true,
        "view_eventlog": true,
        "view_firmware": false,
        "view_fish_history": true,
        "view_flash": false,
        "view_heap": false,
        "view_hibernation": false,
        "view_history": true,
        "view_info": true,
        "view_inputrc": true,
        "view_journal": true,
        "view_kdump": false,
        "view_locale": true,
        "view_dump": false,
        "view_minidump": false,
        "view_nvram": false,
        "view_pagefile": false,
        "view_readline": true,
        "view_rom": false,
        "view_screen": true,
        "view_sram": false,
        "view_swapfile": false,
        "view_syslog": true,
        "view_temp": true,
        "view_termcap": true,
        "view_terminfo": true,
        "view_tmp": true,
        "view_tmux": true,
        "view_trace": true,
        "view_utf8": true,
        "view_verbose": true,
        "view_vimrc": true,
        "view_vmcore": false,
        "view_warn": true,
        "view_zsh_history": true,
        "view_ansi": true,
        "view_appimage": false,
        "view_apt": true,
        "view_big5": true,
        "view_bower": true,
        "view_component": true,
        "view_composer": true,
        "view_conda": true,
        "view_cp1252": true,
        "view_desktop": true,
        "view_dpkg": true,
        "view_mix": true,
        "view_elm_package": true,
        "view_emerge": true,
        "view_euc": true,
        "view_flatpak": false,
        "view_gb2312": true,
        "view_guix": true,
        "view_mod": true,
        "view_sum": true,
        "view_cabal": true,
        "view_stack": true,
        "view_brew": true,
        "view_latin1": true,
        "view_port": true,
        "view_magic": false,
        "view_manifest": true,
        "view_mime": true,
        "view_nix": true,
        "view_npm": true,
        "view_package": true,
        "view_pacman": true,
        "view_pip": true,
        "view_pipfile": true,
        "view_pnpm": true,
        "view_poetry": true,
        "view_pyproject": true,
        "view_requirements": true,
        "view_gem": true,
        "view_cargo": true,
        "view_sbt": true,
        "view_setup": true,
        "view_shift_jis": true,
        "view_snap": false,
        "view_unicode": true,
        "view_urpmi": true,
        "view_yarn": true,
        "view_yum": true,
        "view_zypper": true,
        "view_apollo": true,
        "view_application_properties": true,
        "view_bookshelf": true,
        "view_bootstrap_properties": true,
        "view_bunyan": true,
        "view_commons_logging": true,
        "view_cors": true,
        "view_express": true,
        "view_fastify": true,
        "view_feathers": true,
        "view_gatsby": true,
        "view_gradle_properties": true,
        "view_graphql": true,
        "view_hapi": true,
        "view_helmet": true,
        "view_knex": true,
        "view_koa": true,
        "view_lein": true,
        "view_local_properties": true,
        "view_log4j": true,
        "view_logback": true,
        "view_loguru": true,
        "view_meteor": true,
        "view_mongoose": true,
        "view_morgan": true,
        "view_nest": true,
        "view_next": true,
        "view_nuxt": true,
        "view_objection": true,
        "view_pino": true,
        "view_prisma": true,
        "view_project": true,
        "view_rebar": true,
        "view_relay": true,
        "view_restify": true,
        "view_sails": true,
        "view_sequelize": true,
        "view_slf4j": true,
        "view_socketio": true,
        "view_sveltekit": true,
        "view_tinylog": true,
        "view_typeorm": true,
        "view_vcpp": false,
        "view_solution": true,
        "view_waterline": true,
        "view_websocket": true,
        "view_winston": true,
        "view_workspace": true,
        "view_pbxproj": false,
        "view_xcodeproj": false,
        "view_backstop": true,
        "view_blitz": true,
        "view_chromatic": true,
        "view_concrete5": true,
        "view_craft": true,
        "view_differ": true,
        "view_docusaurus": true,
        "view_doxygen": true,
        "view_drupal": true,
        "view_gemini": true,
        "view_ghost": true,
        "view_gitbook": true,
        "view_grav": true,
        "view_gridsome": true,
        "view_hermione": true,
        "view_hexo": true,
        "view_hugo": true,
        "view_jekyll": true,
        "view_joomla": true,
        "view_jsdoc": true,
        "view_kirby": true,
        "view_looks_same": true,
        "view_magento": true,
        "view_mkdocs": true,
        "view_modx": true,
        "view_nightwatch": true,
        "view_opencart": true,
        "view_oscommerce": true,
        "view_oxid": true,
        "view_percy": true,
        "view_pixelmatch": true,
        "view_prestashop": true,
        "view_protractor": true,
        "view_redwood": true,
        "view_remix": true,
        "view_resemblejs": true,
        "view_shopify": true,
        "view_sphinx": true,
        "view_statamic": true,
        "view_storybook": true,
        "view_strapi": true,
        "view_textpattern": true,
        "view_typedoc": true,
        "view_typo3": true,
        "view_vuepress": true,
        "view_webdriverio": true,
        "view_woocommerce": true,
        "view_wordpress": true,
        "view_wraith": true,
        "view_zencart": true,
        "view_aircrack": false,
        "view_amass": false,
        "view_brave": true,
        "view_burp": false,
        "view_casper": true,
        "view_censys": false,
        "view_charles": true,
        "view_chrome": true,
        "view_curl": true,
        "view_dirb": false,
        "view_dnsrecon": false,
        "view_edge": true,
        "view_ffuf": false,
        "view_fiddler": true,
        "view_fierce": false,
        "view_firefox": true,
        "view_gobuster": false,
        "view_hashcat": false,
        "view_headless": true,
        "view_httpie": true,
        "view_hydra": false,
        "view_ie": true,
        "view_insomnia": true,
        "view_john": false,
        "view_lynx": true,
        "view_maltego": false,
        "view_massdns": false,
        "view_metasploit": false,
        "view_nessus": false,
        "view_nikto": false,
        "view_nmap": false,
        "view_opera": true,
        "view_paw": true,
        "view_phantom": true,
        "view_postman": true,
        "view_safari": true,
        "view_shodan": false,
        "view_slimerjs": true,
        "view_sqlmap": false,
        "view_subfinder": false,
        "view_sublist3r": false,
        "view_tcpdump": false,
        "view_testcafe": true,
        "view_theharvester": false,
        "view_tor": true,
        "view_vivaldi": true,
        "view_w3m": true,
        "view_wget": true,
        "view_wireshark": false,
        "view_zap": false
    }
}
EOF
        echo "✓ Created config.json"
    fi
fi

# Handle other configuration files - preserve existing, copy missing
if [ -d "/app/default-config" ]; then
    echo "Checking for other missing configuration files..."
    
    # Use find to iterate through all files in default config
    find /app/default-config -type f | while read -r src_file; do
        # Get relative path from default config root
        rel_path="${src_file#/app/default-config/}"
        dst_file="/config/$rel_path"
        
        # Skip files we've already handled
        case "$rel_path" in
            local_api/*|config.json)
                continue
                ;;
        esac
        
        # Copy file if it doesn't exist in destination
        if [ ! -f "$dst_file" ]; then
            echo "Copying missing file: $rel_path"
            # Ensure destination directory exists
            mkdir -p "$(dirname "$dst_file")"
            cp "$src_file" "$dst_file"
        fi
    done
    
    echo "✓ Configuration files check completed"
else
    echo "⚠ Warning: No default config found at /app/default-config"
fi

# Remove existing symlinks/directories if they exist and aren't symlinks
echo "Setting up symlinks..."
if [ -d "/www/indexer/.indexer_files" ] && [ ! -L "/www/indexer/.indexer_files" ]; then
    rm -rf /www/indexer/.indexer_files
fi
if [ -d "/www/indexer/files" ] && [ ! -L "/www/indexer/files" ]; then
    rm -rf /www/indexer/files
fi

# Remove existing symlinks to recreate them
if [ -L "/www/indexer/.indexer_files" ]; then
    rm /www/indexer/.indexer_files
fi
if [ -L "/www/indexer/files" ]; then
    rm /www/indexer/files
fi

# Create symlinks
ln -sf /config /www/indexer/.indexer_files
ln -sf /files /www/indexer/files

echo "Symlinks created:"
echo "  /www/indexer/.indexer_files -> /config"
echo "  /www/indexer/files -> /files"

# Set proper ownership for all directories
echo "Setting proper ownership..."
chown -R www-data:www-data /config
chown -R www-data:www-data /files
chown -R www-data:www-data /www/indexer

# Verify symlinks
echo "Verifying symlinks..."
if [ -L "/www/indexer/.indexer_files" ]; then
    echo "✓ .indexer_files symlink created successfully"
else
    echo "✗ Failed to create .indexer_files symlink"
    exit 1
fi

if [ -L "/www/indexer/files" ]; then
    echo "✓ files symlink created successfully"
else
    echo "✗ Failed to create files symlink"
    exit 1
fi

# Test nginx configuration
echo "Testing nginx configuration..."
nginx -t

if [ $? -ne 0 ]; then
    echo "✗ Nginx configuration test failed!"
    exit 1
fi

echo "✓ Nginx configuration test passed"

# Test PHP-FPM configuration
echo "Testing PHP-FPM configuration..."
php-fpm -t

if [ $? -ne 0 ]; then
    echo "✗ PHP-FPM configuration test failed!"
    exit 1
fi

echo "✓ PHP-FPM configuration test passed"

# Display container information
echo ""
echo "5q12's Indexer Container Information:"
echo "====================================="
echo "Environment: Docker"
echo "Index.php location: /www/indexer/index.php"
echo "Config directory: /config (mounted)"
echo "Files directory: /files (mounted)"
echo "Nginx port: 5012"
echo "Version: 1.1.18"
echo ""
echo "Volume mounts should be:"
echo "  -v /host/config:/config"
echo "  -v /host/files:/files"
echo ""
echo "Configuration status:"
if [ -f "/config/config.json" ]; then
    # Try to extract version from config.json
    if command -v php >/dev/null 2>&1; then
        current_version=$(php -r "
            \$config = json_decode(file_get_contents('/config/config.json'), true);
            echo isset(\$config['version']) ? \$config['version'] : 'unknown';
        " 2>/dev/null || echo "unknown")
        echo "  ✓ config.json: Present (version: $current_version)"
    else
        echo "  ✓ config.json: Present"
    fi
else
    echo "  ✗ config.json: Missing"
fi
if [ -d "/config/local_api" ]; then
    echo "  ✓ local_api: Refreshed"
else
    echo "  ✗ local_api: Missing"
fi
if [ -d "/config/zip_cache" ]; then
    echo "  ✓ zip_cache: Ready"
fi
if [ -d "/config/index_cache" ]; then
    echo "  ✓ index_cache: Ready"
fi
echo ""
echo "Starting services..."

# Execute the main command (supervisord)
exec "$@"