if (typeof Swiper !== "undefined") {
	const swiper = new Swiper(".swiper", {
		autoplay: {
			delay: 5000,
		},
		speed: 1000,
		spaceBetween: 100,
		// Optional parameters
		direction: "horizontal",
		loop: true,
	});
}
