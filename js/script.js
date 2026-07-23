document.addEventListener('DOMContentLoaded', () => {
    
    // --- HERO SLIDER LOGIC ---
    const slides = document.querySelectorAll('.slide');
    const title = document.getElementById('hero-title');
    const desc = document.getElementById('hero-desc');
    const sliderContainer = document.getElementById('hero');
    
    if (slides.length && title && desc && sliderContainer) {
        let currentIndex = 0;
        let slideInterval;
        let startX = 0;
        let endX = 0;

        function goToSlide(index) {
            slides[currentIndex].classList.remove('active');
            currentIndex = (index + slides.length) % slides.length; 
            slides[currentIndex].classList.add('active');
            
            title.textContent = slides[currentIndex].getAttribute('data-title');
            desc.textContent = slides[currentIndex].getAttribute('data-desc');
        }

        function nextSlide() { goToSlide(currentIndex + 1); }
        function prevSlide() { goToSlide(currentIndex - 1); }

        function startSlider() {
            slideInterval = setInterval(nextSlide, 5000); 
        }

        function resetSlider() {
            clearInterval(slideInterval);
            startSlider();
        }

        // Mobile Touch Swipe for Slider
        sliderContainer.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        }, { passive: true });

        sliderContainer.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            const swipeThreshold = 50; 
            if (startX - endX > swipeThreshold) {
                nextSlide();
                resetSlider();
            } else if (endX - startX > swipeThreshold) {
                prevSlide();
                resetSlider();
            }
        }

        startSlider();
    }

    // --- FORM SUCCESS STATE HANDLER ---
    // 1. Check the URL for the success flag
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('status') === 'success') {
        // 2. Find the form and the success box
        const contactForm = document.querySelector('.contact-form');
        const successBox = document.getElementById('form-success-state');
        
        // 3. Swap them
        if (contactForm && successBox) {
            contactForm.style.display = 'none';
            successBox.style.display = 'block';
        }
        
        // 4. Smooth scroll down to the contact section so the user sees it
        const contactSection = document.getElementById('contact');
        if (contactSection) {
            // Using a slight timeout ensures the DOM has fully rendered the un-hidden box before scrolling
            setTimeout(() => {
                contactSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
        
        // 5. Clean the URL (removes ?status=success from the address bar)
        window.history.replaceState(null, null, window.location.pathname + window.location.hash);
    }

});