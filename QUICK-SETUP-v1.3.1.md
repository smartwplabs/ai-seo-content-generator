# v1.3.1 Quick Setup Guide

## 🚀 Get Started in 5 Minutes!

---

## 📦 **Installation**

1. Download: `ai-seo-content-generator-v1.3.1-FINAL.zip`
2. WordPress → Plugins → Deactivate old version
3. Delete old plugin folder
4. Upload v1.3.1 ZIP
5. Activate

**Existing settings preserved!** ✅

---

## ⚙️ **REQUIRED: Configure Timing for YOUR Site**

### **Step 1: Measure Your Site Speed** ⏱️

1. Go to Products
2. Edit any product
3. Click "Update" button
4. **Start timer when you click**
5. **Stop timer when page finishes reloading**
6. Write down the time

**Example:**
- User with ShortPixel: **17.26 seconds**
- User without image optimization: **3.5 seconds**

---

### **Step 2: Set Timing Slider** 🎚️

1. Go to **AI SEO Content Generator → Tools**
2. Scroll to **"📊 Rank Math Score Calculation"** section
3. Find **"Score Calculation Wait Time"** slider
4. **Set to:** Your measured time + 1-2 seconds
   - Measured 17 sec → Set to **18-19 seconds**
   - Measured 3 sec → Set to **4-5 seconds**
   - Measured 10 sec → Set to **11-12 seconds**
5. Click **"Save Changes"**

**Default is 5 seconds** - works for most sites, but adjust to YOUR speed!

---

### **Step 3: Post-Save Delay** (Optional)

If you have **Permalink Manager** or **image plugins**:

1. Set **"Post-Save Processing Delay"** to **1-2 seconds**
2. This lets other plugins process before we update fields
3. Click **"Save Changes"**

**Default is 1 second** - usually fine!

---

## ⚡ **OPTIONAL: Enable Image Bypass** (Huge Speedup!)

### **If You Have ShortPixel/Smush/Imagify/EWWW:**

1. Go to **AI SEO Content Generator → Tools**
2. Scroll to **"🔌 Third-Party Integrations"**
3. Find **"⚡ Disable Image Optimization During Generation"**
4. You should see: **"📊 Detected: ShortPixel"** (or your plugin)
5. **Check the box** ✅
6. Click **"Save Changes"**

**Result:** 
- Before: 17 seconds per product
- After: 3-5 seconds per product
- **Saves 12-15 seconds EACH product!** 🚀

### **When to Use Image Bypass:**

✅ **Enable for:** Bulk text content generation  
❌ **Disable for:** Uploading new product images  
❌ **Disable for:** Changing existing product images

---

## 🧪 **Test It!**

### **Generate Content on 1 Test Product:**

1. Go to Products
2. Select 1 product
3. Click "Generate Content"
4. **Watch the timing!**
5. Check if score calculation works

**If score shows "NOT SET":**
- Your wait time is too short
- Increase slider by 2-3 seconds
- Try again

**If score works:**
- ✅ Perfect! You're ready for bulk!

---

## 📊 **Your Configuration Cheat Sheet**

### **Fast Site (< 5 sec load):**
```
Score Wait Time: 5 seconds
Post-Save Delay: 1 second
Image Bypass: Off (not needed)
```

### **Medium Site (5-10 sec load):**
```
Score Wait Time: 10 seconds
Post-Save Delay: 1-2 seconds
Image Bypass: Optional (modest speedup)
```

### **Slow Site with ShortPixel (15-20 sec load):**
```
Score Wait Time: 18 seconds ← YOUR SETTING!
Post-Save Delay: 2 seconds
Image Bypass: ✅ ON (HUGE speedup!)
```

---

## ✨ **What Changed from v1.3.0?**

**1. Timing is now adjustable!** ⏱️
- No more hardcoded 7-second wait
- Works on ANY site speed
- Set to YOUR measured time

**2. Image optimizer bypass!** ⚡
- Optional speedup for ShortPixel/Smush users
- 3-5x faster bulk operations
- Safe for text-only updates

**3. Better UI!** 🎨
- Sliders update in real-time
- Better tooltips
- No more password prompts

**4. Better title prompts!** 📝
- Titles now START with focus keyword
- Better SEO scores
- Fixes keyword density issues

---

## 🎯 **Troubleshooting**

### **Score shows "NOT SET":**
→ Increase "Score Wait Time" by 3-5 seconds

### **Permalinks not updating:**
→ Increase "Post-Save Delay" to 2 seconds

### **Alt tags not updating:**
→ Increase "Post-Save Delay" to 2 seconds

### **Still too slow:**
→ Enable "Disable Image Optimization" checkbox

### **Image bypass checkbox disabled (grayed out):**
→ No supported image plugin detected - feature not needed!

---

## 🎉 **You're Ready!**

**Settings configured?** ✅  
**Test product works?** ✅  
**Ready for bulk operations!** 🚀

**Enjoy your faster, smarter AI SEO plugin!**

---

## 📞 **Need Help?**

Check the full changelog: `CHANGELOG-v1.3.1.md`

**Common Issue:** "Score calculation still failing"
**Fix:** Your site is slower than you measured. Add 5 more seconds to the slider!

**Remember:** 
- Too short = doesn't work ❌
- Too long = works fine, just slower ✅
- **Start high, then reduce** if you want to optimize!

---

**Happy generating!** 🎊
