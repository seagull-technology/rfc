(function () {
    "use strict";

    let currentTab = 0;

    const ActiveTab = (n) => {
        if (n == 0) {
            document.getElementById("step1").classList.add("active");
            document.getElementById("step1").classList.remove("done");

            document.getElementById("step2").classList.remove("active");
            document.getElementById("step2").classList.remove("done");
        }

        if (n == 1) {
            document.getElementById("step1").classList.add("done");

            document.getElementById("step2").classList.add("active");
            document.getElementById("step2").classList.remove("done");
        }
    };

    const showTab = (n) => {
        const x = document.getElementsByTagName("fieldset");

        for (let i = 0; i < x.length; i++) {
            x[i].style.display = "none";
        }

        x[n].style.display = "block";
        ActiveTab(n);
    };

  const nextBtnFunction = (n) => {
    const x = document.getElementsByTagName("fieldset");

    currentTab = currentTab + n;

    if (currentTab >= x.length) {
        currentTab = x.length - 1;
        return;
    }

    if (currentTab < 0) {
        currentTab = 0;
    }

    showTab(currentTab);
};

    // Next
    const nextbtn = document.querySelectorAll('.next');
    nextbtn.forEach(btn => {
        btn.addEventListener('click', function () {
            nextBtnFunction(1);
        });
    });

    // Previous
    const prebtn = document.querySelectorAll('.previous');
    prebtn.forEach(btn => {
        btn.addEventListener('click', function () {
            nextBtnFunction(-1);
        });
    });

    // أول تحميل
    showTab(currentTab);


    document.getElementById("step1").addEventListener("click", () => {
    currentTab = 0;
    showTab(currentTab);
});

document.getElementById("step2").addEventListener("click", () => {
    currentTab = 1;
    showTab(currentTab);
});
})();