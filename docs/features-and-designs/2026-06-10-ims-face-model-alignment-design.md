# Intern Face ID Model & Orientation Alignment Spec

**Date:** 2026-06-10  
**Status:** Approved  
**Author:** Antigravity  

## 1. Context & Problem Statement
The HRIS Kiosk face verification system displayed low similarity scores for interns compared to employees:
1. **Initial Issue (Model Mismatch):** Similarity scores were near-zero (`1.50%`) because the Python registration server used `buffalo_l` (ResNet-50) while the Kiosk/mobile clients ran `buffalo_sc` (MobileFaceNet).
2. **Subsequent Issue (Mirroring Discrepancy):** Even after changing the model to `buffalo_sc`, scores hover around **~55%** (barely passing or failing the 52% threshold), while employee similarity scores are **70–80%+**.
3. **API Save Failures:** The registration API would fail to save the registration because the queries targeted `registered_at`, which does not exist in the `interns` table.
4. **Weak Error Handling:** Errors from the Python face embedding service (e.g. no face/multiple faces) were swallowed by the PHP backend, returning generic errors.

### Causes
- **Mirroring Discrepancy:** The mobile `hris-app` captures mirrored front-camera photos but applies a horizontal flip to store **unmirrored** embeddings. The kiosk app matches these against unmirrored live frames. The web-based Intern Registration portal previewed mirrored frames but also saved them as **mirrored** embeddings (by scaling the capture canvas `ctx.scale(-1, 1)`). This meant the kiosk was matching an unmirrored live face against a mirrored stored embedding.
- **Database Schema Mismatch:** The table `interns` defines the column as `face_registered_at`, but the public registration API code used `registered_at`.
- **Generic Error cURL Responses:** cURL responses from the Python embedding service did not propagate detailed server error messages back to the frontend.

---

## 2. Proposed & Implemented Changes

### A. Python face embedding server (`intern_face_reg_server/app.py`)
1. **Initialize the matching model:** Change model initialization to use `buffalo_sc`.
2. **Backwards compatibility:** Handle both `images` (sent by IMS admin dashboard registration) and `photos` (sent by IMS intern portal registration) payload keys.
3. **Status flags:** Return both `success: true` and `ok: true`.

### B. Intern Registration Canvas Mirroring Removal (`INTERN-MANAGEMENT-SYSTEM/register_intern.php`)
1. **Primary & Silent Capture Canvas Alignment:** Remove `ctx.translate(canvas.width, 0)` and `ctx.scale(-1, 1)` canvas mirroring transformations during webcam capture. Draw the naturally unmirrored browser stream directly to the canvas so stored images/embeddings are unmirrored.

### C. Database Schema Correction (`INTERN-REGISTRATION/api/save_intern_registration.php`)
1. **Column Alignment:** Update all references of `registered_at` to `face_registered_at` in the `INSERT` and `UPDATE` SQL statements to match the database table structure.

### D. Detailed cURL Error Propagation
1. **Error Handling:** Refactor cURL handlers in both `register_intern.php` and `save_intern_registration.php` to decode the response body from the face server and propagate detailed validation messages (e.g. "No face detected in photo 3") back to the user interface.

### E. Database Face Profile Reset
Clear existing registered face data for the 2 interns in the local IMS database (`tdt_ims`) to prompt a clean re-registration:
- **John Asher Manit** (ID 3)
- **Keith Antonio** (ID 6)

---

## 3. Detailed Specifications

### File: `C:/Users/Keith/HRIS/HRIS-KIOSK/intern_face_reg_server/app.py`
```python
face_app = FaceAnalysis(name='buffalo_sc', root=insightface_root)

# Accept either 'images' or 'photos'
images_base64 = data.get('images') or data.get('photos')

# Return both ok and success flags
return jsonify({'success': True, 'ok': True, 'embeddings': embeddings})
```

### File: `C:/Users/Keith/HRIS/INTERN-MANAGEMENT-SYSTEM/register_intern.php` (Lines 779-783 / 796-800)
```javascript
// Before:
// ctx.save();
// ctx.translate(canvas.width, 0);
// ctx.scale(-1, 1);
// ctx.drawImage(webcam, sx1, sy1, minDim1, minDim1, 0, 0, canvas.width, canvas.height);
// ctx.restore();

// After:
ctx.drawImage(webcam, sx1, sy1, minDim1, minDim1, 0, 0, canvas.width, canvas.height);
```

### Database Migration
```sql
UPDATE interns 
SET face_embedding = NULL, 
    face_registered_at = NULL, 
    qr_code = NULL 
WHERE id IN (3, 6);
```

---

## 4. Verification & Testing Plan

### Step 1: Model Initialization
- Start the python face service and check that it downloads/loads `buffalo_sc` models successfully.

### Step 2: Request/Response Compatibility
- Call the `/embed` endpoint using `images` payload format and verify it returns `{"success": true, "ok": true, "embeddings": [...]}`.
- Call the `/embed` endpoint using `photos` payload format and verify it returns the same response format.

### Step 3: Verification Matching & Scoring
- Re-register an intern face.
- Scan their QR code at the Kiosk and verify face matches with high similarity (>70%).

