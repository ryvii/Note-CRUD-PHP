<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = "";
$success_message = "";

// Handle Create Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_note'])) {
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    
    // Server-side validation
    if (empty($title)) {
        $error_message = "Title is required.";
    } elseif (strlen($title) > 255) {
        $error_message = "Title must be less than 255 characters.";
    } elseif (empty($content)) {
        $error_message = "Content is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $content);
        
        if ($stmt->execute()) {
            $success_message = "Note created successfully!";
        } else {
            $error_message = "Failed to create note. Please try again.";
        }
        $stmt->close();
    }
    
    // Clear POST data to prevent resubmission
    header("Location: notes.php");
    exit();
}

// Handle Update Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_note'])) {
    $note_id = filter_var($_POST['note_id'], FILTER_VALIDATE_INT);
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    
    // Validate input
    if (!$note_id) {
        $error_message = "Invalid note ID.";
    } elseif (empty($title)) {
        $error_message = "Title is required.";
    } elseif (strlen($title) > 255) {
        $error_message = "Title must be less than 255 characters.";
    } elseif (empty($content)) {
        $error_message = "Content is required.";
    } else {
        // Verify note belongs to user and update
        $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssii", $title, $content, $note_id, $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Note updated successfully!";
        } elseif ($stmt->affected_rows === 0) {
            $error_message = "Note not found or no changes made.";
        } else {
            $error_message = "Failed to update note.";
        }
        $stmt->close();
    }
    
    header("Location: notes.php");
    exit();
}

// Handle Delete Note
if (isset($_GET['delete'])) {
    $note_id = filter_var($_GET['delete'], FILTER_VALIDATE_INT);
    
    if ($note_id) {
        // Verify note belongs to user before deleting
        $stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $note_id, $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Note deleted successfully!";
        } else {
            $error_message = "Note not found or already deleted.";
        }
        $stmt->close();
    } else {
        $error_message = "Invalid note ID.";
    }
    
    header("Location: notes.php");
    exit();
}

// Handle Edit Note
$edit_note = null;
if (isset($_GET['edit'])) {
    $note_id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    
    if ($note_id) {
        $stmt = $conn->prepare("SELECT id, title, content FROM notes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $note_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $edit_note = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Fetch all notes for current user
$notes = [];
$stmt = $conn->prepare("SELECT id, title, content, created_at, updated_at FROM notes WHERE user_id = ? ORDER BY updated_at DESC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="noteStyle.css">
    <title>My Notes - <?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Notes 📝</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?>!</span>
                <a href="login_register.php?logout" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
            </div>
        </div>
        
        <!-- Create/Edit Note Form -->
        <div class="form-box">
            <h2><?php echo $edit_note ? 'Edit Note' : 'Create New Note'; ?></h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="message error_message">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?> 
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="message success_message">
                    <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <form action="notes.php" method="POST">
                <?php if ($edit_note): ?>
                    <input type="hidden" name="note_id" value="<?php echo $edit_note['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Note Title</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        placeholder="Enter note title..."
                        value="<?php echo htmlspecialchars($edit_note['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        required
                        maxlength="255"
                    >
                </div>

                <div class="form-group">
                    <label for="content">Note Content</label>
                    <textarea
                        id="content"
                        name="content"
                        placeholder="Enter note content..."
                        required
                    ><?php echo htmlspecialchars($edit_note['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <button type="submit" name="<?php echo $edit_note ? 'update_note' : 'create_note'; ?>">
                    <?php echo $edit_note ? 'Update Note' : 'Save Note'; ?>
                </button>
                
                <?php if ($edit_note): ?>
                    <a href="notes.php" class="cancel-btn">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Display Notes -->
        <?php if (!empty($notes)): ?>
            <div class="notes-section">
                <h2>Your Notes (<?php echo count($notes); ?>)</h2>
                <div class="notes-grid">
                    <?php foreach ($notes as $note): ?>
                        <div class="note-box">
                            <div class="note-header">
                                <div class="note-title">
                                    <?php echo htmlspecialchars($note['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="note-actions">
                                    <a href="notes.php?edit=<?php echo $note['id']; ?>" class="edit-btn" title="Edit">✏️</a>
                                    <a href="notes.php?delete=<?php echo $note['id']; ?>" class="delete-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this note?')">🗑️</a>
                                </div>
                            </div>
                            
                            <div class="note-content">
                                <?php 
                                    $content = htmlspecialchars($note['content'], ENT_QUOTES, 'UTF-8');
                                    echo strlen($content) > 300 ? substr($content, 0, 300) . '...' : $content;
                                ?>
                            </div>
                            
                            <div class="note-footer">
                                <div class="note-timestamp">
                                    🕐 Created: <?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?>
                                </div>
                                <?php if ($note['created_at'] != $note['updated_at']): ?>
                                    <div class="note-timestamp">
                                    ✏️ Updated: <?php echo date('Y-m-d H:i', strtotime($note['updated_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>    
            <div class="no-notes">
                <p>📭 No notes yet. Create your first note above!</p>
            </div>
        <?php endif; ?>
    </div>
</body> 
</html>