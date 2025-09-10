# User Guide

## Table of Contents
- [Getting Started](#getting-started)
- [Interface Overview](#interface-overview)
- [Navigation](#navigation)
- [File Operations](#file-operations)
- [Sorting & Organization](#sorting--organization)
- [Mobile Usage](#mobile-usage)
- [Tips & Best Practices](#tips--best-practices)

## Getting Started

### First Access

1. **Navigate to the indexer URL**
   ```
   https://yourdomain.com/path/to/indexer/
   ```

2. **Interface loads automatically**
   - No login required (unless configured by administrator)
   - Directory contents display immediately
   - All features available based on configuration

### What You'll See

The indexer displays:
- **Files and folders** in the current directory
- **File information** (size, modification date, type)
- **Navigation tools** (breadcrumbs, sorting options)
- **Action buttons** (download, view, depending on configuration)

## Interface Overview

### Header Section
```
Index of /current/directory/path
content / documents / projects
```

- **Title**: Shows current directory path
- **Breadcrumbs**: Clickable navigation trail
- **Quick navigation** to any parent directory

### File Listing

Each item shows:

| Element | Description |
|---------|-------------|
| **Icon** | Visual indicator of file type or folder |
| **Name** | Clickable file/folder name |
| **Size** | File size or calculated directory size |
| **Modified** | Last modification date and time |
| **Actions** | Download/view buttons (if enabled) |

### Sort Controls

- **Options menu (⋯)**: Access all sorting options
- **Column headers**: Click to sort by that column
- **Sort indicators**: Arrows show current sort direction

## Navigation

### Directory Navigation

#### Using Breadcrumbs
Click any part of the path to jump to that directory:
```
content / documents / projects
   ↑        ↑          ↑
 Root    Documents  Current
```

#### Using Folder Links
- **Single-click** folder names to enter directories
- **Blue folder icons** indicate navigable directories
- **Parent directory (..)** link goes up one level

#### Browser Navigation
- **Back button**: Return to previous directory
- **Forward button**: Return after going back
- **Bookmark**: Save frequently accessed directories

### Path Information

**Current location** is always shown in:
- **Page title**: `Index of /path/to/directory`
- **Breadcrumb trail**: Interactive navigation
- **Browser address bar**: Direct URL access

## File Operations

### Viewing Files

Files display differently based on administrator configuration:

#### Browser Viewable Files
**Open directly in browser:**
- **Text files**: `.txt`, `.md`, `.json`, `.xml`
- **Source code**: `.php`, `.js`, `.py`, `.html`, `.css`
- **Images**: `.png`, `.jpg`, `.gif`, `.svg`
- **Videos**: `.mp4`, `.webm` (browser-supported)
- **Audio**: `.mp3`, `.ogg`, `.aac`
- **Documents**: `.pdf`

**How to view:**
- Click file name
- Opens in new tab/window
- Uses appropriate browser viewer

#### Download-Only Files
**Trigger immediate download:**
- **Executables**: `.exe`, `.dll`, `.app`
- **Archives**: `.zip`, `.rar`, `.7z`
- **Binary files**: Various system files
- **Security files**: Keys, certificates

### Downloading Files

#### Individual Files
1. **Click "DL" button** next to file name
2. **File downloads** to your default download location
3. **Original filename** is preserved
4. **No size limits** (depends on server configuration)

#### Folder Downloads
1. **Click "ZIP" button** next to folder name
2. **ZIP archive created** with folder contents
3. **Directory structure preserved** within archive
4. **Automatic cleanup** of temporary files

#### Download Availability
Download buttons may be missing if:
- Administrator disabled downloads
- File type restricted for security
- Insufficient server permissions

### File Information

#### File Sizes
- **Bytes (B)**: Small files under 1KB
- **Kilobytes (KB)**: Files 1KB - 1MB
- **Megabytes (MB)**: Files 1MB - 1GB
- **Gigabytes (GB)**: Files over 1GB

#### Directory Sizes
- **Calculated recursively**: Includes all subdirectory contents
- **Real-time calculation**: May take time for large directories
- **Cached results**: Faster on subsequent visits

#### Modification Dates
- **Format**: `YYYY-MM-DD HH:MM`
- **Server timezone**: Based on server location
- **Sort capability**: Click column to sort by date

## Sorting & Organization

### Quick Sort Options

**Click column headers** for immediate sorting:
- **Name**: Alphabetical A-Z ↔ Z-A
- **Size**: Small-to-large ↔ Large-to-small  
- **Modified**: Oldest ↔ Newest

**Active sort** shown with arrow indicators (↑ ↓)

### Advanced Sort Menu

**Click options (⋯) for full menu:**

#### By Name
- **A-Z**: Standard alphabetical order
- **Z-A**: Reverse alphabetical order

#### By Size  
- **Small to Large**: Smallest files first
- **Large to Small**: Largest files first

#### By Date
- **Oldest First**: Earliest modifications first
- **Newest First**: Most recent modifications first

#### By Type
- **A-Z**: File extensions alphabetically
- **Z-A**: File extensions reverse alphabetically

### Organization Features

#### Folders First
- **Directories** always appear before files
- **Parent directory (..)** appears first when present
- **Consistent organization** regardless of sort type

#### Mixed Content Handling
- **Files and folders** sorted separately
- **Logical grouping** by type and purpose
- **Visual distinction** with icons and formatting

## Mobile Usage

### Mobile Interface Features

**Responsive design** adapts to mobile screens:
- **Touch-friendly** buttons and links
- **Optimized layout** for narrow screens
- **Simplified interface** on small displays
- **Gesture support** follows browser standards

### Mobile Navigation

#### Touch Controls
- **Tap folder names** to navigate
- **Tap file names** to view or download
- **Pinch to zoom** for detailed viewing
- **Swipe gestures** for browser navigation

#### Mobile-Specific Features
- **Larger touch targets** for easier interaction
- **Simplified sort menu** with essential options
- **Optimized file display** for mobile viewing
- **Reduced visual clutter** on small screens

### Mobile Limitations

#### Viewing Limitations
- **Limited file type support** in mobile browsers
- **Video playback** may vary by device
- **PDF viewing** depends on browser capabilities
- **Large files** may cause performance issues

#### Download Considerations
- **Storage space** limitations on mobile devices
- **Network speed** affects download performance
- **File management** more complex on mobile
- **ZIP extraction** requires additional apps

## Tips & Best Practices

### Efficient Navigation

#### Quick Access
1. **Bookmark frequently used directories** in your browser
2. **Use breadcrumbs** for fast parent directory access
3. **Copy directory URLs** for direct access
4. **Use browser history** for recently visited directories

#### Finding Files
1. **Sort by modification date** to find recent changes
2. **Sort by name** for alphabetical browsing
3. **Sort by size** to identify large files
4. **Use browser search (Ctrl+F)** to find specific filenames

### File Management

#### Before Downloading
1. **Check file sizes** to estimate download time
2. **Verify file types** to ensure compatibility
3. **Consider ZIP downloads** for multiple files
4. **Check available storage space**

#### Viewing Files
1. **Try viewing before downloading** when possible
2. **Use browser back button** to return to directory
3. **Right-click links** to copy URLs for sharing
4. **Open in new tabs** to keep directory listing open

### Performance Tips

#### For Large Directories
1. **Allow time for initial loading** - subsequent visits are faster
2. **Use specific directory URLs** instead of browsing from root
3. **Close unnecessary browser tabs** to free memory
4. **Clear browser cache** if experiencing display issues

#### For Slow Connections
1. **Avoid large file downloads** on slow connections
2. **Use text file viewing** instead of downloading
3. **Consider ZIP downloads** for multiple small files
4. **Be patient with large directory listings**

### Troubleshooting

#### Common Issues

**Files not appearing:**
- Administrator may have filtered certain file types
- Hidden files require special configuration
- Check with administrator about access policies

**Cannot download files:**
- Downloads may be disabled by administrator
- Check browser popup blockers
- Verify sufficient disk space
- Try different browser if issues persist

**Slow performance:**
- Large directories take time to load initially
- Network speed affects all operations
- Clear browser cache and cookies
- Try different browser or device

#### Browser Compatibility

**Best experience with:**
- **Chrome/Chromium** (latest versions)
- **Firefox** (latest versions)  
- **Safari** (latest versions)
- **Edge** (latest versions)

**Limited support:**
- **Internet Explorer** (outdated, not recommended)
- **Older mobile browsers** (reduced functionality)

### Getting Help

If you encounter issues:
1. **Refresh the page** (F5 or Ctrl+R)
2. **Clear browser cache** and cookies
3. **Try a different browser**
4. **Contact the server administrator**
5. **Check browser console** for error messages (F12)

Remember that specific functionality may vary based on administrator configuration and server environment.

---

**Related Documentation:**
- [Configuration Guide](configuration.md) - Administrator settings
- [Troubleshooting Guide](troubleshooting.md) - Common issues and solutions special configuration

**Cannot download files**
- Downloads may be disabled by administrator
- Check browser popup blockers
- Verify sufficient disk space for downloads

**Slow performance**
- Large directories take time to load initially
- Subsequent visits should be faster due to caching
- Network speed affects file viewing and downloads

**View issues**
- Some file types require specific browser plugins
- Mobile devices may not support all file viewing
- PDF and office documents depend on browser capabilities

### Browser Compatibility

**Recommended browsers:**
- Chrome/Chromium (latest versions)
- Firefox (latest versions)
- Safari (latest versions)
- Edge (latest versions)

**Limitations with older browsers:**
- Reduced file type viewing support
- Potential interface display issues
- Limited mobile responsiveness

### Getting Help

If you encounter issues:
1. Try refreshing the page (F5 or Ctrl+R)
2. Clear browser cache and cookies
3. Try a different browser
4. Contact the server administrator
5. Check browser console for error messages (F12)

## Administrator Notes for Users

### Customization

The appearance and behavior of the indexer may vary depending on:
- Administrator configuration settings
- Security policies in place
- Network and server environment
- Organizational requirements

### Access Control

Access to files and folders may be:
- Unrestricted (full access to directory contents)
- Filtered (certain file types or directories hidden)
- Restricted (downloads disabled or limited)
- Monitored (usage tracking enabled)

### Feature Availability

Available features depend on administrator settings:
- File downloads may be enabled or disabled
- Folder ZIP downloads may be available
- File viewing capabilities may vary
- Sorting and display options may be customized

This user guide covers the standard features available in 5q12's Indexer. Specific functionality may vary based on administrator configuration and server environment.