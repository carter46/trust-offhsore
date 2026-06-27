<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

$companyName = getSetting('company_name', 'FedEx');
$currentYear = date('Y');
$footerCompany = replacePlaceholders(getSetting('footer_company_name', $companyName));
$footerCopyright = replacePlaceholders(getSetting('footer_copyright', $companyName));
$hqAddress = replacePlaceholders(getSetting('contact_hq_address', "1200 Logistics Blvd, Suite 500\nSan Francisco, CA 94107\nUnited States"));
?>

<!-- Footer CTA -->
<section class="footer-cta-bg py-36 md:py-40 text-center min-h-[340px] flex items-center">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-6">
            <a class="bg-yellow-400 text-black px-10 py-4 rounded font-bold uppercase tracking-widest text-sm hover:bg-yellow-500 transition-colors flex items-center justify-center" href="/contact">
                <span class="material-symbols-outlined mr-2 text-base">mail</span> Get In Touch
            </a>
            <a class="bg-white text-black px-10 py-4 rounded font-bold uppercase tracking-widest text-sm hover:bg-gray-100 transition-colors flex items-center justify-center" href="/track">
                <span class="material-symbols-outlined mr-2 text-base">inventory_2</span> Track
            </a>
        </div>
    </div>
</section>

<!-- Main Footer -->
<footer class="bg-slate-900 text-white pt-20 pb-10">
    <div class="container mx-auto px-4 md:px-12 grid md:grid-cols-4 gap-12 border-b border-slate-800 pb-12">
        <div>
            <h4 class="text-lg font-bold mb-6 border-l-4 border-yellow-400 pl-3">Recent Posts</h4>
            <p class="text-gray-500 text-sm">Stay tuned for updates from <?php echo htmlspecialchars($footerCompany); ?>.</p>
        </div>
        <div>
            <h4 class="text-lg font-bold mb-6 border-l-4 border-yellow-400 pl-3">Photo Gallery</h4>
            <div class="grid grid-cols-2 gap-2">
                <img alt="Gallery" class="rounded cursor-pointer hover:opacity-75 object-cover h-24 w-full" src="/asset/home/gallery-1.jpg"/>
                <img alt="Gallery" class="rounded cursor-pointer hover:opacity-75 object-cover h-24 w-full" src="/asset/home/gallery-2.jpg"/>
            </div>
        </div>
        <div>
            <h4 class="text-lg font-bold mb-6 border-l-4 border-yellow-400 pl-3">Quick Links</h4>
            <ul class="space-y-2 text-gray-400 text-sm">
                <li><a class="hover:text-yellow-400 transition-colors" href="/contact">Contact Us</a></li>
                <li><a class="hover:text-yellow-400 transition-colors" href="/our-services">Clearing</a></li>
                <li><a class="hover:text-yellow-400 transition-colors" href="/our-services">Courier Services</a></li>
                <li><a class="hover:text-yellow-400 transition-colors" href="/our-services">Repacking</a></li>
                <li><a class="hover:text-yellow-400 transition-colors" href="/contact">Our Team</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-lg font-bold mb-6 border-l-4 border-yellow-400 pl-3">Newsletter</h4>
            <form class="space-y-4" onsubmit="event.preventDefault();">
                <input class="w-full bg-slate-800 border-none rounded focus:ring-yellow-400 text-sm text-white placeholder-gray-500 px-4 py-3" placeholder="Your Email Address" type="email"/>
                <button type="submit" class="w-full bg-yellow-400 text-black font-bold py-3 rounded text-sm uppercase hover:bg-yellow-500 transition-colors">Subscribe</button>
            </form>
        </div>
    </div>
    <div class="container mx-auto px-4 md:px-12 pt-8 text-center text-xs text-gray-500">
        &copy; <?php echo $currentYear; ?> <?php echo htmlspecialchars($footerCopyright); ?>. All rights reserved.
    </div>
</footer>

<?php include __DIR__ . '/cookie-banner.php'; ?>
<script src="/js/cookie-handler.js"></script>

<?php if (!empty($showSmartsupp) && !empty($smartsuppKey)): ?>
<script type="text/javascript">
var _smartsupp = _smartsupp || {};
_smartsupp.key = '<?php echo htmlspecialchars($smartsuppKey); ?>';
window.smartsupp||(function(d) {
  var s,c,o=smartsupp=function(){ o._.push(arguments)};o._=[];
  s=d.getElementsByTagName('script')[0];c=d.createElement('script');
  c.type='text/javascript';c.charset='utf-8';c.async=true;
  c.src='https://www.smartsuppchat.com/loader.js?';s.parentNode.insertBefore(c,s);
})(document);
</script>
<noscript>Powered by <a href="https://www.smartsupp.com" target="_blank">Smartsupp</a></noscript>
<?php endif; ?>
</div>
</body>
</html>
