# Attendance Export Feature

## Overview
The attendance system now includes a new "Spreadsheet Format" export option that generates attendance reports in a traditional grid format similar to manual attendance sheets.

## Features

### Spreadsheet Format Export
- **File**: `teacher/export_attendance_spreadsheet.php`
- **Format**: Excel-compatible HTML table with styling
- **Layout**: Grid format with student names, daily attendance, and summary columns

### Key Features
1. **Header Section**: 
   - Date information with day names
   - Faculty/teacher information
   - Session type labels

2. **Student Grid**:
   - Student names in rows
   - Daily attendance status in columns
   - Color-coded attendance status:
     - Green (P): Present
     - Red (A): Absent  
     - Yellow (L): Late

3. **Summary Columns**:
   - IL Count: Number of absent days
   - Total: Number of present days
   - Per 100: Attendance percentage

4. **Professional Styling**:
   - Blue headers for section titles
   - Yellow headers for session information
   - Color-coded attendance cells
   - Summary footer with legend

## How to Use

### For Teachers
1. Navigate to the Attendance Management page
2. Select a class from the dropdown
3. Choose the month for export using the month selector
4. Click "Export Report" dropdown
5. Select "Export as Spreadsheet Format"
6. The file will download as an Excel-compatible .xls file

### File Structure
The exported file includes:
- Title row with class name, subject, and month
- Date headers with day names
- Faculty information
- Student attendance grid
- Summary statistics
- Legend and generation timestamp

## Technical Details

### Database Queries
- Fetches attendance records for the specified class and month
- Groups data by student and date
- Calculates summary statistics

### Styling
- Uses CSS classes for consistent formatting
- Color-coded cells for different attendance statuses
- Responsive table layout
- Excel-compatible HTML structure

### Security
- Teacher authentication required
- Class ownership verification
- Input validation and sanitization

## File Locations
- **Export Script**: `teacher/export_attendance_spreadsheet.php`
- **UI Integration**: `teacher/attendance.php`
- **Database**: Uses existing `attendance` table

## Browser Compatibility
- Works with all modern browsers
- Excel-compatible format
- Can be opened in Microsoft Excel, Google Sheets, or LibreOffice Calc

## Future Enhancements
- Custom date range selection
- Additional summary statistics
- Multiple class export
- PDF format option
- Email integration
