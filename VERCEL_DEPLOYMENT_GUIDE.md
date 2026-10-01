# ✅ Vercel Deployment Guide - Boarding House Rental System

## Pre-Deployment Checklist

### ✅ Home Page Updates Completed
- [x] Fixed navigation links (removed `#anchor` links, pointing to proper PHP pages)
- [x] Updated search form to submit to `browse-rooms.php` with proper field names
- [x] Removed logo dependency (using emoji icon instead)
- [x] Integrated API data fetching with fallback to sample data
- [x] Fixed CTA button links throughout the page
- [x] Fixed modal reservation button to link to login page

---

## 🚀 Vercel Deployment Steps

### 1. **Prepare Your Repository**
```bash
# If not already a Git repo
cd c:\xampp1\htdocs\BHsystemBACKUP
git init
git add .
git commit -m "Initial commit: Boarding House Rental System"
git remote add origin <your-github-repo-url>
git push -u origin main
```

### 2. **Environment Variables** (Create in Vercel dashboard)
```
DB_HOST=your_database_host
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=boarding_house_db
API_URL=https://your-vercel-url
```

### 3. **Important Notes for Vercel**

**Vercel is primarily for static hosting or serverless functions.** Since your project uses PHP:

#### Option A: Use Vercel Functions (Recommended)
- Rename `.php` files to `.ts` or `.js` in `api/` folder
- Rewrite PHP logic to Node.js/TypeScript
- This requires code conversion

#### Option B: Use Traditional Hosting (Better for PHP)
Consider hosting on:
- **Heroku** (supports PHP)
- **Railway** 
- **Render**
- **Hostinger** or **000webhost**

#### Option C: Hybrid Approach
- Host static files (HTML, CSS, JS) on Vercel
- Keep PHP backend on traditional hosting
- Use CORS and API calls to connect

---

## 📋 Files to Monitor

| File | Status | Notes |
|------|--------|-------|
| `home.html` | ✅ Updated | All links fixed |
| `home.js` | ✅ Updated | Now fetches from API with fallback |
| `home.css` | ✅ Ready | No changes needed |
| `api/rooms.php` | ⚠️ Check | Ensure returns JSON format |
| `browse-rooms.php` | ⚠️ Check | Ensure accepts GET params |
| Database Connection | ⚠️ Check | Must work with hosting provider |

---

## 🔗 API Response Format Expected

Your `api/rooms.php` should return:

```json
{
  "success": true,
  "rooms": [
    {
      "id": "room-1",
      "name": "Green View Boarding House",
      "location": "DAPA, Siargao",
      "price": 1500,
      "roomType": "Single Room",
      "availability": "Available",
      "rooms": 6,
      "amenities": ["Wi-Fi", "Electricity", "Water"],
      "img": "image-url-here"
    }
  ]
}
```

---

## 🧪 Testing Before Deployment

### Local Testing
1. Run your website locally:
   ```bash
   # Using XAMPP
   php -S localhost:8000
   ```

2. Test all home page features:
   - ✅ Navigation links work
   - ✅ Search form submits correctly
   - ✅ Listings load from API
   - ✅ Modal opens/closes
   - ✅ Mobile menu works

3. Check browser console for errors:
   - Open DevTools (F12)
   - Check Console tab for API fetch errors

### Performance Check
- Use Lighthouse (DevTools) to audit
- Optimize image sizes
- Minify CSS/JS if needed

---

## 📱 Mobile Responsiveness

✅ Already implemented:
- Mobile hamburger menu
- Responsive grid layouts
- Touch-friendly buttons
- Meta viewport tag

Test on multiple devices:
- iPhone 12/14
- Samsung Galaxy
- iPad
- Desktop browsers (Chrome, Firefox, Safari)

---

## 🔒 Security Checklist

- [ ] HTTPS enabled (Vercel auto-enables)
- [ ] Input validation on all forms
- [ ] SQL injection prevention in PHP
- [ ] XSS protection in JavaScript
- [ ] CSRF tokens implemented
- [ ] Environment variables not exposed
- [ ] No sensitive data in frontend code

---

## ❌ Known Issues & Fixes

### Issue: "API returns 404"
**Solution:** Ensure `api/rooms.php` path is correct and file exists.

### Issue: "Images don't load"
**Solution:** Use absolute URLs or verify relative paths work on hosting platform.

### Issue: "Search form not working"
**Solution:** Ensure `browse-rooms.php` exists and accepts GET parameters.

### Issue: "Styles not loading"
**Solution:** Clear browser cache (Ctrl+Shift+Del) and check CSS file paths.

---

## 📞 Support Resources

- **Vercel Docs:** https://vercel.com/docs
- **PHP Hosting Alternatives:** https://www.php.net/supported-versions
- **CORS Issues:** https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS

---

## ✨ Next Steps

1. **Choose hosting platform** (Vercel for static, or traditional host for PHP)
2. **Test locally** with all features
3. **Deploy** to your chosen platform
4. **Monitor** for errors in production
5. **Iterate** based on user feedback

---

**Last Updated:** 2026-08-14
**Home Page Version:** 2.0 (API-Ready)
