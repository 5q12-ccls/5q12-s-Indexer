# Configuration Guide

## Table of Contents
- [Overview](#overview)
- [Configuration File Structure](#configuration-file-structure)
- [Main Settings](#main-settings)
- [File Type Controls](#file-type-controls)
- [Advanced Filtering](#advanced-filtering)
- [Configuration Examples](#configuration-examples)
- [Management & Updates](#management--updates)

## Overview

5q12's Indexer uses a JSON-based configuration system that controls all aspects of behavior, from file display to system performance. The configuration file is located at `.indexer_files/config.json` and must be created manually.

### Quick Configuration

**Enable high performance:**
```json
{"main": {"cache_type": "sqlite"}}
```

**Disable downloads:**
```json
{"main": {"disable_file_downloads": true, "disable_folder_downloads": true}}
```

**Show hidden files:**
```json
{"main": {"index_hidden": true}}
```

## Configuration File Structure

```json
{
  "version": "1.0",
  "main": {
    // Core system settings
  },
  "exclusions": {
    // Controls which file types are indexed (shown in listings)
  },
  "viewable_files": {
    // Controls which files can be viewed vs downloaded
  }
}
```

## Main Settings

### Performance Settings

#### Cache Type
```json
{
  "main": {
    "cache_type": "sqlite"  // "sqlite" or "json"
  }
}
```

| Option | Performance | Compatibility | Use Case |
|--------|-------------|---------------|----------|
| `sqlite` | **High** (5-10x faster) | Requires SQLite3 extension | Production, large directories |
| `json` | Standard | Universal | Simple setups, small directories |

#### Icon Type
```json
{
  "main": {
    "icon_type": "default"  // "default", "minimal", "emoji", "disabled"
  }
}
```

| Option | Description | Use Case |
|--------|-------------|----------|
| `default` | Full icon library with type-specific icons | Standard usage, visual file identification |
| `minimal` | Generic file/folder icons only | Bandwidth-limited environments |
| `emoji` | Unicode emoji icons (📄/📁) | No external resources, universal compatibility |
| `disabled` | No icons displayed | Text-only interfaces, accessibility |

**Icon behavior:**
- `default` - Uses full icon library (`.indexer_files/icons/`)
- `minimal` - Shows only `folder.png` and `non-descript-default-file.png`
- `emoji` - Uses Unicode file (📄) and folder (📁) symbols
- `disabled` - Removes icon column entirely, adjusts layout

All icons are stored and served locally from `.indexer_files/icons/`

### Download Controls

#### File Downloads
```json
{
  "main": {
    "disable_file_downloads": true,  // Removes the Download options from file action menus
    "disable_folder_downloads": true // Removes the Download options from folder action menus
  }
}
```

**Use cases for disabling:**
- Read-only file browser
- Security compliance
- Bandwidth conservation
- Content protection

### Display Controls

#### Hidden Files
```json
{
  "main": {
    "index_hidden": true  // Show files starting with "."
  }
}
```

**Hidden items include:**
- `.htaccess`, `.env`, `.gitignore`
- `.git/`, `.vscode/`, `.DS_Store`
- Configuration and system files

#### Index All (⚠️ Use with caution)
```json
{
  "main": {
    "index_all": false  // Override all filtering when true
  }
}
```

**When enabled, shows:**
- All security-sensitive files
- All file types regardless of other settings

## File Type Controls

### Exclusions (Indexing)

Controls which file types appear in directory listings.

#### Common Programming Languages
```json
{
  "exclusions": {
    "index_php": false,    // Hide PHP files
    "index_js": true,      // Show JavaScript files
    "index_py": true,      // Show Python files
    "index_java": true,    // Show Java files
    "index_cpp": true      // Show C++ files
  }
}
```

#### Documents and Media
```json
{
  "exclusions": {
    "index_txt": true,     // Show text files
    "index_pdf": true,     // Show PDF documents
    "index_docx": true,    // Show Word documents
    "index_png": true,     // Show PNG images
    "index_mp4": true      // Show MP4 videos
  }
}
```

#### Security-Sensitive Files (Default: hidden)
```json
{
  "exclusions": {
    "index_key": false,           // Hide cryptographic keys
    "index_secret": false,        // Hide secret files
    "index_passwd": false,        // Hide password files
    "index_authorized_keys": false // Hide SSH keys
  }
}
```

### Viewable Files

Controls which files open in browser vs download.

#### Text and Code Files
```json
{
  "viewable_files": {
    "view_php": true,      // View PHP source
    "view_js": true,       // View JavaScript
    "view_json": true,     // View JSON files
    "view_md": true        // View Markdown
  }
}
```

#### Media Files
```json
{
  "viewable_files": {
    "view_png": true,      // View images in browser
    "view_mp4": true,      // View videos in browser
    "view_pdf": true       // View PDFs in browser
  }
}
```

#### Security Files (Default: download only)
```json
{
  "viewable_files": {
    "view_exe": false,     // Download executables
    "view_key": false,     // Download keys
    "view_cert": false     // Download certificates
  }
}
```

## Advanced Filtering

### Deny List

Exclude specific files, folders, or patterns.

#### Basic Examples
```json
{
  "main": {
    "deny_list": "logs, temp, admin, .git"
  }
}
```

#### Pattern Examples
```json
{
  "main": {
    "deny_list": "uploads/.exe*, config/.env*, cache*, private/*"
  }
}
```

**Pattern Types:**
- `folder` - Exact folder exclusion
- `folder*` - Folder and all contents
- `folder/*` - Folder and subfolders
- `folder/.ext*` - All files with extension in folder
- `file.ext` - Specific file

### Allow List

Force inclusion, overriding other exclusions.

#### Override Examples
```json
{
  "main": {
    "allow_list": "public*, docs/readme.txt, admin/debug.php"
  }
}
```

#### Priority System
1. **Conflicting rules** (same path in both lists) → Both ignored
2. **Allow list** → Takes priority over deny list
3. **Extension settings** → Apply as defaults
4. **Fallback** → Show by default

## Configuration Examples

### High-Security Environment
```json
{
  "main": {
    "access_url": "https://example.net",
    "cache_type": "sqlite",
    "icon_type": "minimal",
    "disable_file_downloads": true,
    "disable_folder_downloads": true,
    "index_hidden": false,
    "deny_list": "logs, .git, .env*, config/secrets*, admin"
  },
  "exclusions": {
    "index_php": false,
    "index_key": false,
    "index_secret": false
  },
  "viewable_files": {
    "view_php": false,
    "view_key": false
  }
}
```

### Development Environment
```json
{
  "main": {
    "cache_type": "sqlite",
    "icon_type": "default",
    "index_hidden": true,
    "index_all": true,
    "allow_list": "src*, docs*, config/dev.json"
  },
  "exclusions": {
    "index_php": true,
    "index_js": true,
    "index_py": true
  },
  "viewable_files": {
    "view_php": true,
    "view_js": true,
    "view_py": true
  }
}
```

### Public File Server
```json
{
  "main": {
    "cache_type": "sqlite",
    "icon_type": "emoji",
    "deny_list": "admin, private, .htaccess, config"
  },
  "exclusions": {
    "index_php": false,
    "index_key": false
  },
  "viewable_files": {
    "view_pdf": true,
    "view_png": true,
    "view_mp4": true,
    "view_php": false
  }
}
```

### Media Server
```json
{
  "main": {
    "cache_type": "sqlite",
    "icon_type": "default",
    "deny_list": "system, config, logs"
  },
  "exclusions": {
    "index_png": true,
    "index_jpg": true,
    "index_mp4": true,
    "index_mp3": true,
    "index_pdf": true,
    "index_php": false
  },
  "viewable_files": {
    "view_png": true,
    "view_jpg": true,
    "view_mp4": true,
    "view_mp3": true,
    "view_pdf": true
  }
}
```

### Troubleshooting Configuration

#### Configuration Not Applied
1. **Check JSON syntax**
   ```bash
   python -m json.tool .indexer_files/config.json
   ```
2. **Clear cache**
   ```bash
   rm -rf .indexer_files/index_cache/*
   ```
3. **Verify file permissions**
   ```bash
   ls -la .indexer_files/config.json
   ```

#### Performance Issues
1. **Enable SQLite caching**
   ```json
   {"main": {"cache_type": "sqlite"}}
   ```
2. **Simplify deny/allow patterns**
3. **Review extension settings**

#### Security Concerns
1. **Verify sensitive files are excluded**
2. **Check deny list effectiveness**
3. **Review download permissions**

---

**Related Documentation:**
- [Security Guide](security.md) - Security-focused configuration
- [Troubleshooting Guide](troubleshooting.md) - Configuration issues
- [Installation Guide](installation.md) - Configuration setup