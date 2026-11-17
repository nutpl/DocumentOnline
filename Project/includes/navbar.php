<!-- Navbar -->
<nav class="fixed top-0 left-0 w-full z-50 bg-black/90 backdrop-blur-md shadow-lg">

    <div class="max-w-7xl mx-auto">

        <div class="flex items-center justify-between h-20 px-6">

            <!-- Logo -->

            <a href="index.php" class="flex items-center">

                <img src="../assets/images/Logo_rmuti.png" class="h-12">

                <div class="ml-4">

                    <h1 class="text-white text-xl font-bold leading-none">
                        Dynamic Document
                    </h1>

                    <p class="text-red-500 text-sm">
                        Engineering & Technology
                    </p>

                </div>

            </a>

            <!-- Desktop Menu -->

            <div class="hidden lg:flex items-center space-x-8">

                <a href="#home"
                    class="text-gray-300 hover:text-red-500 transition duration-300">

                    หน้าแรก

                </a>

                <a href="#about"
                    class="text-gray-300 hover:text-red-500 transition duration-300">

                    เกี่ยวกับระบบ

                </a>

                <a href="#document"
                    class="text-gray-300 hover:text-red-500 transition duration-300">

                    แบบคำร้อง

                </a>

                <a href="#news"
                    class="text-gray-300 hover:text-red-500 transition duration-300">

                    ข่าวประชาสัมพันธ์

                </a>

                <a href="#contact"
                    class="text-gray-300 hover:text-red-500 transition duration-300">

                    ติดต่อเรา

                </a>

            </div>

            <!-- Right -->

            <div class="hidden lg:flex items-center space-x-4">

                <a href="register.php"
                    class="border border-red-600 text-red-500 hover:bg-red-600 hover:text-white px-5 py-2 rounded-lg transition">

                    สมัครสมาชิก

                </a>

                <a href="login.php"
                    class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-lg transition">

                    เข้าสู่ระบบ

                </a>

            </div>

            <!-- Mobile Button -->

            <button
                id="menu-btn"
                class="lg:hidden text-white">

                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-8 w-8"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor">

                    <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"/>

                </svg>

            </button>

        </div>

    </div>

</nav>

<!-- Mobile Menu -->

<div id="mobile-menu"
    class="hidden fixed top-20 left-0 w-full bg-gray-900 shadow-xl z-40">

    <div class="flex flex-col p-6 space-y-4">

        <a href="../index.php"
            class="text-white hover:text-red-500">

            หน้าแรก

        </a>

        <a href="#about"
            class="text-white hover:text-red-500">

            เกี่ยวกับระบบ

        </a>

        <a href="#document"
            class="text-white hover:text-red-500">

            แบบคำร้อง

        </a>

        <a href="#news"
            class="text-white hover:text-red-500">

            ข่าวประชาสัมพันธ์

        </a>

        <a href="#contact"
            class="text-white hover:text-red-500">

            ติดต่อเรา

        </a>

        <hr class="border-gray-700">

        <a href="register.php"
            class="bg-gray-800 text-center text-white rounded-lg py-3">

            สมัครสมาชิก

        </a>

        <a href="login.php"
            class="bg-red-700 text-center text-white rounded-lg py-3">

            เข้าสู่ระบบ

        </a>

    </div>

</div>

<script>

const menuBtn = document.getElementById('menu-btn');

const mobileMenu = document.getElementById('mobile-menu');

menuBtn.addEventListener('click', () => {

    mobileMenu.classList.toggle('hidden');

});

</script>