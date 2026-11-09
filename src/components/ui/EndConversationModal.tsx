"use client";

import Image from "next/image";
import { useEffect, useState } from "react";

interface EndConversationModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit?: (rating: number) => void;
}

function StarIcon({ filled }: { filled: boolean }) {
  return (
    <Image
      src={filled ? "/svg/filled-star.svg" : "/svg/unfilled-star.svg"}
      alt=""
      width={36}
      height={36}
    />
  );
}

export function EndConversationModal({
  isOpen,
  onClose,
  onSubmit,
}: EndConversationModalProps) {
  const [rating, setRating] = useState(0);
  const [hovered, setHovered] = useState(0);

  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOpen, onClose]);

  useEffect(() => {
    if (!isOpen) setRating(0);
  }, [isOpen]);

  if (!isOpen) return null;

  const handleSubmit = () => {
    onSubmit?.(rating);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 bg-white md:bg-black/40 md:backdrop-blur-sm flex items-center justify-center md:p-4">
      <div className="relative w-full h-full md:h-auto md:max-w-3xl md:min-h-[480px] bg-white md:rounded-[32px] md:shadow-2xl overflow-hidden flex flex-col md:flex-row">
        <button
          type="button"
          onClick={onClose}
          aria-label="Close"
          className="absolute top-4 right-4 md:top-6 md:right-6 z-20 w-10 h-10 md:w-12 md:h-12 rounded-full bg-[#F3F4F6] flex items-center justify-center text-[#9CA3AF] hover:bg-[#E5E7EB] transition-colors"
        >
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2.5}
            strokeLinecap="round"
          >
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>

        {/* Text side */}
        <div className="flex-1 md:flex-initial flex flex-col items-center text-center justify-center px-6 pt-10 pb-4 md:p-14 md:w-[60%] z-10">
          <h2 className="text-black font-bold text-2xl md:text-[32px] leading-snug md:leading-[1.2] max-w-sm">
            How was your conversation with Aira, our AI Chatbot?
          </h2>
          <p className="text-black/80 mt-3 md:mt-4 text-base md:text-[17px] max-w-xs md:max-w-[280px]">
            Your response is anonymous. Your response will help us improve.
          </p>

          <div className="flex items-center gap-4 md:gap-3 mt-6 md:mt-8">
            {[1, 2, 3, 4, 5].map((star) => (
              <button
                key={star}
                type="button"
                aria-label={`${star} star${star > 1 ? "s" : ""}`}
                onMouseEnter={() => setHovered(star)}
                onMouseLeave={() => setHovered(0)}
                onClick={() => setRating(star)}
              >
                <StarIcon filled={star <= (hovered || rating)} />
              </button>
            ))}
          </div>

          <button
            type="button"
            onClick={handleSubmit}
            disabled={rating === 0}
            className="mt-8 md:mt-10 w-auto px-8 py-3.5 md:px-12 md:py-4 bg-primary text-white font-medium text-sm md:text-[15px] rounded-full disabled:opacity-50 btn-hover-lift tracking-wide"
          >
            SUBMIT RESPONSE
          </button>
        </div>

        {/* Illustration side */}
        <div className="relative h-72 sm:h-80 flex-none md:h-auto md:min-h-0 md:w-[40%] md:shrink-0 pointer-events-none">
          <Image
            src="/images/Man.webp"
            alt=""
            fill
            className="object-contain object-bottom md:object-right-bottom"
          />
        </div>
      </div>
    </div>
  );
}
