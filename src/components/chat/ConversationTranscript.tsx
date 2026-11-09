"use client";

import { useEffect, useRef } from "react";
import type { ChatMessage } from "@/hooks/useVoiceSession";

interface Props {
  messages: ChatMessage[];
  className?: string;
}

/** Live conversation bubbles; keeps the newest message in view. */
export function ConversationTranscript({ messages, className = "" }: Props) {
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = scrollRef.current;
    if (el) el.scrollTop = el.scrollHeight;
  }, [messages]);

  return (
    <div ref={scrollRef} className={`overflow-y-auto space-y-3 md:pr-2 max-md:[scrollbar-width:none] max-md:[&::-webkit-scrollbar]:hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-accent [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-[#7C88FD] ${className}`}>
      {messages.map((m) => (
        <div key={m.id} className={`flex ${m.role === "user" ? "justify-end" : "justify-start"}`}>
          <p
            data-role={m.role}
            className={`max-w-[85%] rounded-2xl px-4 py-2.5 text-sm md:text-base leading-relaxed whitespace-pre-wrap text-left ${
              m.role === "user"
                ? "bg-primary text-white rounded-br-md"
                : "bg-white/80 text-primary rounded-bl-md"
            } ${m.interrupted ? "opacity-70" : ""}`}
          >
            {m.text}
            {m.interrupted && "…"}
          </p>
        </div>
      ))}
    </div>
  );
}
