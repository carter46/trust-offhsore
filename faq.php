<?php
include __DIR__ . '/includes/header.php';

$page = getPageContent('faq');
$content = $page['content'] ?? null;

$hero = $content['hero'] ?? [
    'heading' => 'Frequently Asked Questions',
    'subtitle' => 'Common questions about {company}'
];

$faqItems = $content['items'] ?? [];
$companyName = getSetting('company_name', 'FedEx');
?>

<section class="page-hero-bg text-white py-16 lg:py-20">
    <div class="container mx-auto px-4 md:px-12 text-center">
        <div class="w-16 h-1 bg-yellow-400 mx-auto mb-4"></div>
        <h1 class="text-white text-4xl lg:text-5xl font-extrabold mb-4 tracking-tight">
            <?php echo htmlspecialchars(replacePlaceholders($hero['heading'])); ?>
        </h1>
        <p class="text-gray-200 text-xl lg:text-2xl font-normal">
            <?php echo htmlspecialchars(replacePlaceholders($hero['subtitle'])); ?>
        </p>
    </div>
</section>

<section class="py-24 px-4 lg:px-10 dot-pattern dot-pattern-gray">
    <div class="max-w-4xl mx-auto">
        <?php if (empty($faqItems)): ?>
            <div class="text-center py-12">
                <p class="text-gray-600 text-lg">No FAQ items available. Please check back later.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($faqItems as $index => $item): ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm hover:shadow-md transition-shadow border-t-4 border-t-yellow-400">
                        <button class="faq-question w-full px-6 py-5 text-left bg-white hover:bg-gray-50 transition-colors flex items-center justify-between"
                                onclick="toggleFaq(<?php echo $index; ?>)"
                                aria-expanded="false">
                            <span class="font-bold text-slate-800 pr-4 text-lg"><?php echo htmlspecialchars(replacePlaceholders($item['question'] ?? '')); ?></span>
                            <span class="material-symbols-outlined text-yellow-500 faq-icon transform transition-transform flex-shrink-0">expand_more</span>
                        </button>
                        <div class="faq-answer hidden px-6 py-5 bg-white border-t border-gray-200">
                            <div class="text-gray-600 leading-relaxed text-base">
                                <?php echo nl2br(htmlspecialchars(replacePlaceholders($item['answer'] ?? ''))); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function toggleFaq(index) {
    const buttons = document.querySelectorAll('.faq-question');
    const answers = document.querySelectorAll('.faq-answer');
    const icons = document.querySelectorAll('.faq-icon');

    const button = buttons[index];
    const answer = answers[index];
    const icon = icons[index];
    const isExpanded = button.getAttribute('aria-expanded') === 'true';

    buttons.forEach((btn, i) => {
        btn.setAttribute('aria-expanded', 'false');
        answers[i].classList.add('hidden');
        icons[i].classList.remove('rotate-180');
        btn.classList.remove('bg-gray-50');
        btn.classList.add('bg-white');
    });

    if (!isExpanded) {
        button.setAttribute('aria-expanded', 'true');
        answer.classList.remove('hidden');
        icon.classList.add('rotate-180');
        button.classList.remove('bg-white');
        button.classList.add('bg-gray-50');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
